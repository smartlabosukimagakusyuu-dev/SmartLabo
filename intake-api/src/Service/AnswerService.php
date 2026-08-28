<?php
/**
 * HP Intake API — 回答の保存と提出（SSOT §2.3 / §3 / §6）。
 *
 *  - 受け付けるのは JSON 11分類のみ。**未知キーは拒否**
 *  - 楽観ロック（intake_answers.version）
 *  - 二重送信は **submission_id（UUID v4）による冪等化 ＋ status ＋ DB一意制約**で防ぐ
 *    （SSOT v1.3 §6.4。status だけでは「応答が消えた後の再送」を区別できない）
 *  - 提出履歴には**件数だけ**を残す（回答本文・項目名を残さない。SSOT §2.4）
 *  - 回答内容・PII・**submission_id** をログへ出さない（SSOT §10.7）
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Service;

use SmartLabo\Intake\Db;
use SmartLabo\Intake\AnswerSchema;
use SmartLabo\Intake\Migrator;
use SmartLabo\Intake\Support\Clock;

final class AnswerService
{
    /** 配列分類の要素数上限（SSOT §3 の設計既定値） */
    public const ARRAY_CAPS = [
        'menus'          => 60,
        'staff'          => 30,
        'image_metadata' => 60,
    ];

    /** 1分類あたりのエンコード後の上限バイト（body 1MB の内訳として） */
    public const SECTION_MAX_BYTES = 262144;

    /**
     * 提出に必要な条件（SSOT v1.9 §3.0.2）。
     *
     * ★**生成物**を参照するだけにする。ここへ一覧を手で書かない。
     *   v1.8 まではこのファイルに22件を書き写しており、
     *   SSOT §3 が必須と定める39件と食い違っていた（4F-R2 で判明）。
     *   同じものを2か所に置けば、いつか片方だけが古くなる。
     *
     * @var list<string>
     */
    public const REQUIRED_PATHS = AnswerSchema::STORE_REQUIRED_NON_EMPTY;

    /** 写真の最低枚数（SSOT §3.10 / 仕様書 §12.3） */
    public const MIN_IMAGES = 8;

    /** 法的確認の件数（L-01〜L-13） */
    public const RIGHTS_CONFIRMATIONS = 13;

    public function __construct(
        private readonly Db $db,
        private readonly Clock $clock,
        private readonly Audit $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function get(int $caseId): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM intake_answers WHERE intake_case_id = :id');
        $stmt->execute([':id' => $caseId]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new \RuntimeException('answers row missing');
        }

        $sections = [];
        foreach (Migrator::ANSWER_SECTIONS as $section) {
            $decoded            = json_decode((string)$row[$section . '_json'], true);
            $sections[$section] = is_array($decoded) ? $decoded : [];
        }

        // ★保存済みの値を無条件に信用しない（SSOT v1.8 §3.0-9 / §11.3）。
        //   4F-R1 より前に入った未知キーが残っていても、ここから先へは出さない。
        //   店舗の復元・管理画面・書き出しは、すべてこの戻り値を使う。
        //   ★未知キーがあるだけで落とさない。正式な値はそのまま返す。
        $sections = AnswerValidator::filter($sections);

        return [
            'version'        => (int)$row['version'],
            'schema_version' => (int)$row['schema_version'],
            'sections'       => $sections,
            'updated_at'     => (string)$row['updated_at'],
        ];
    }

    /**
     * 途中保存（部分更新・楽観ロック）。
     * @param array<string,mixed> $sections
     * @return array{ok:bool,error?:string,version?:int}
     */
    public function save(int $caseId, array $sections, int $clientVersion): array
    {
        if ($sections === []) {
            return ['ok' => false, 'error' => 'bad_request'];
        }

        // ★正式構造（§3 の11分類・129パス）に無いキーが1つでもあれば、
        //   **要求そのものを拒否**する。黙って捨てて保存しない（SSOT v1.8 §3.0-9）。
        //   トランザクションへ入る前に判定するので、DBは1バイトも変わらない。
        //   ★どのキーが不正だったかは返さない（内部の一覧を推測させない）。
        //   ★店舗は Smart Labo 設定（§3.12）を書けない。混ざっていたら丸ごと拒否する。
        if (AnswerValidator::check($sections, AnswerValidator::AUDIENCE_STORE)['ok'] !== true) {
            return ['ok' => false, 'error' => 'bad_request'];
        }

        foreach ($sections as $name => $value) {
            if (!in_array($name, Migrator::ANSWER_SECTIONS, true)) {
                return ['ok' => false, 'error' => 'bad_request']; // 未知キーは拒否
            }
            if (!is_array($value)) {
                return ['ok' => false, 'error' => 'bad_request'];
            }
            if (isset(self::ARRAY_CAPS[$name]) && count($value) > self::ARRAY_CAPS[$name]) {
                return ['ok' => false, 'error' => 'bad_request']; // 上限超過は保存拒否（切り捨てない）
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            if ($encoded === false || strlen($encoded) > self::SECTION_MAX_BYTES) {
                return ['ok' => false, 'error' => 'payload_too_large'];
            }
        }

        // ★店舗は分類をまるごと送ってくる。素直に上書きすると
        //   Smart Labo 設定（§3.12）が毎回消えるので、保存済みの分を残す。
        $current = $this->get($caseId)['sections'];

        $set    = ['version = version + 1', 'updated_at = :now'];
        $params = [':now' => $this->clock->iso(), ':id' => $caseId, ':version' => $clientVersion];
        foreach ($sections as $name => $value) {
            $merged = AnswerValidator::mergeKeeping(
                $current[$name] ?? [],
                (array)$value,
                AnswerValidator::AUDIENCE_STORE,
                $name
            );
            $set[]               = $name . '_json = :' . $name;
            $params[':' . $name] = json_encode($merged, JSON_UNESCAPED_UNICODE);
        }

        $stmt = $this->db->pdo()->prepare(
            'UPDATE intake_answers SET ' . implode(', ', $set) . '
              WHERE intake_case_id = :id AND version = :version'
        );
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'error' => 'conflict'];
        }

        $this->audit->record($caseId, 'answer_saved', 'ok');

        return ['ok' => true, 'version' => $clientVersion + 1];
    }

    /* ============================================ 必須の評価（4F-R3） */

    /**
     * 値が「回答済み」か。
     *
     * ★SSOT §3.0-4 のとおり、**未入力は `null` / `""` / `[]`**。
     *   `false` はここに入らない。真偽の項目では `false` も**回答**である。
     */
    private static function isAnswered(mixed $value, string $type): bool
    {
        if ($type === 'bool') {
            return is_bool($value);           // ★null も文字列も数値も回答ではない
        }
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';       // enum の未選択（""）も未回答
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * 正式パスの型を構造から引く（`basic.parking.type` のような子キーも辿る）。
     * @return array{0:string,1:array<string,mixed>} [型, ノード]
     */
    private static function nodeOf(string $path): array
    {
        $parts   = explode('.', $path);
        $section = array_shift($parts);
        $node    = AnswerSchema::STRUCTURE[$section] ?? ['type' => 'object', 'fields' => []];

        foreach ($parts as $part) {
            $bag  = $node['fields'] ?? $node['item'] ?? [];
            $node = $bag[$part] ?? ['type' => 'scalar'];
        }

        return [$node['type'], $node];
    }

    /**
     * 正式パスの値を集める。
     *
     * ★途中に配列（`objects`）があれば、要素ごとに枝分かれする。
     *   `menus.name` は「全メニューの name」を意味する。
     *
     * @param array<string,mixed> $sections
     * @return list<array{0:string,1:mixed,2:bool}> [表示用パス, 値, キーが存在したか]
     */
    private static function collect(array $sections, string $path): array
    {
        $parts   = explode('.', $path);
        $section = array_shift($parts);
        $node    = AnswerSchema::STRUCTURE[$section] ?? null;
        if ($node === null) {
            return [];
        }

        $cursors = [[$section, $sections[$section] ?? null, array_key_exists($section, $sections)]];

        foreach ($parts as $part) {
            $next = [];
            foreach ($cursors as [$label, $value, $exists]) {
                if ($node['type'] === 'objects') {
                    // 配列。要素ごとに分かれる
                    foreach (is_array($value) ? $value : [] as $i => $row) {
                        $next[] = [
                            $label . '[' . (int)$i . '].' . $part,
                            is_array($row) ? ($row[$part] ?? null) : null,
                            is_array($row) && array_key_exists($part, $row),
                        ];
                    }
                    continue;
                }
                $next[] = [
                    $label . '.' . $part,
                    is_array($value) ? ($value[$part] ?? null) : null,
                    is_array($value) && array_key_exists($part, $value),
                ];
            }
            $bag     = $node['fields'] ?? $node['item'] ?? [];
            $node    = $bag[$part] ?? ['type' => 'scalar'];
            $cursors = $next;
        }

        return $cursors;
    }

    /**
     * 指定した必須集合を評価して、不足パスを返す。
     *
     * ★返すのは**パスだけ**。値・氏名・連絡先は返さない。
     *
     * @param array<string,mixed> $sections
     * @param list<string> $nonEmpty  値が入っていること
     * @param list<string> $keyOnly   キーが存在すること（正式な空値を認める）
     * @param list<string> $elements  配列要素・object の子キー
     * @return list<string>
     */
    private static function missingFor(array $sections, array $nonEmpty, array $keyOnly, array $elements = []): array
    {
        $missing = [];

        foreach ($nonEmpty as $path) {
            [$type] = self::nodeOf($path);
            foreach (self::collect($sections, $path) as [$label, $value, $exists]) {
                if (!$exists || !self::isAnswered($value, $type)) {
                    $missing[] = $label;
                }
            }
        }

        foreach ($keyOnly as $path) {
            [$type] = self::nodeOf($path);
            foreach (self::collect($sections, $path) as [$label, $value, $exists]) {
                // ★キーがあることが条件。ただし真偽の項目は型まで見る
                //   （`"false"` や `0` や `null` を回答として通さない）。
                //   判定は isAnswered() ひとつに寄せる。同じ規則を2か所に書かない。
                if (!$exists || ($type === 'bool' && !self::isAnswered($value, 'bool'))) {
                    $missing[] = $label;
                }
            }
        }

        // 配列要素・object の子キー。★要素が無ければ何も要求しない
        foreach ($elements as $path) {
            [$type] = self::nodeOf($path);
            foreach (self::collect($sections, $path) as [$label, $value, $exists]) {
                // ★真偽なら `false` も回答（isAnswered が判断する）。
                //   それ以外は「値が入っていること」。
                if (!$exists || !self::isAnswered($value, $type)) {
                    $missing[] = $label;
                }
            }
        }

        return $missing;
    }

    /**
     * Smart Labo が書き出し前に設定する項目の不足（SSOT v1.9 §3.0.2）。
     *
     * ★店舗の提出は妨げない。書き出しの直前にだけ効く。
     * ★「設定した」＝キーが存在すること。該当が無い場合も明示的に記録する。
     *
     * @return list<string>
     */
    public function missingAdminSettings(int $caseId): array
    {
        $sections = $this->get($caseId)['sections'];
        $missing  = [];

        foreach (AnswerSchema::ADMIN_REQUIRED_FOR_EXPORT as $path) {
            foreach (self::collect($sections, $path) as [$label, $value, $exists]) {
                unset($value);
                if (!$exists) {
                    $missing[] = $label;
                }
            }
        }

        // ★キーがあるだけでは足りない（4F-R4）。中身の条件も見る。
        //   空でよい項目（予約URL・外部サービス）と、空では困る項目
        //   （送信先・保管方法）が混ざっているため、規則は生成物が持つ。
        foreach (AnswerValidator::adminValueErrors($sections) as $path) {
            if (!in_array($path, $missing, true)) {
                $missing[] = $path;
            }
        }

        return $missing;
    }

    /**
     * Smart Labo 管理設定の現在値（管理画面の表示用）。
     * ★店舗の回答は返さない。管理パスだけを取り出す。
     *
     * @return array<string,array<string,mixed>>
     */
    public function adminSettings(int $caseId): array
    {
        $sections = $this->get($caseId)['sections'];
        $out      = [];

        foreach (AnswerSchema::ADMIN_PATHS as $path) {
            [$section, $key] = explode('.', $path, 2);
            if (is_array($sections[$section] ?? null) && array_key_exists($key, $sections[$section])) {
                $out[$section][$key] = $sections[$section][$key];
            }
        }

        return $out;
    }

    /**
     * Smart Labo 管理設定を保存する（§3.12 / 代表判断 Q4）。
     *
     * ★店舗の回答には触れない。管理パス以外のキーが来たら**丸ごと拒否**する。
     * ★呼べるのは管理画面だけ（認証・CSRF・Origin は呼び出し側で検査する）。
     *
     * @param array<string,mixed> $sections
     * @return array{ok:bool,error?:string}
     */
    public function saveAdminSettings(int $caseId, array $sections): array
    {
        if ($sections === []) {
            return ['ok' => false, 'error' => 'bad_request'];
        }
        if (AnswerValidator::check($sections, AnswerValidator::AUDIENCE_ADMIN)['ok'] !== true) {
            return ['ok' => false, 'error' => 'bad_request'];
        }

        // ★中身の条件（SSOT v1.10 §3.12）。1件でも満たさなければ**5件とも保存しない**。
        //   ★どのパスが悪かったかは返すが、**入力された値は返さない**。
        $bad = AnswerValidator::adminValueErrors($sections);
        if ($bad !== []) {
            return ['ok' => false, 'error' => 'invalid_value', 'paths' => $bad];
        }

        $current = $this->get($caseId)['sections'];
        $names   = [];
        $params  = [':id' => $caseId, ':now' => $this->clock->iso()];

        foreach ($sections as $name => $value) {
            // ★店舗の回答は1バイトも触らない。管理パスだけを差し替える
            $merged = AnswerValidator::mergeKeeping(
                $current[$name] ?? [],
                (array)$value,
                AnswerValidator::AUDIENCE_ADMIN,
                $name
            );
            $names[]                 = $name . '_json = :' . $name;
            $params[':' . $name]     = json_encode($merged, JSON_UNESCAPED_UNICODE);
        }

        $this->db->pdo()->prepare(
            'UPDATE intake_answers SET ' . implode(', ', $names) . ', updated_at = :now
              WHERE intake_case_id = :id'
        )->execute($params);

        // ★値を書かない。設定したという事実だけ
        $this->audit->record($caseId, 'admin_settings_saved', 'ok');

        return ['ok' => true];
    }

    /**
     * 提出に必要な条件を評価する。
     * ★不足は**パスのみ**を返す（値・氏名・連絡先を返さない）。
     * @return array{field_count:int,missing:list<string>}
     */
    public function evaluate(int $caseId): array
    {
        $s = $this->get($caseId)['sections'];

        // ★必須の一覧は**生成物**から来る（SSOT v1.9 §3.0.2）。
        //   画面・API・管理画面・書き出しが同じ集合を見るので、
        //   「画面では止まるのに API では通る」状態が作れない。
        $missing = self::missingFor(
            $s,
            AnswerSchema::STORE_REQUIRED_NON_EMPTY,
            AnswerSchema::STORE_REQUIRED_KEY_ALLOW_EMPTY,
            AnswerSchema::ARRAY_ELEMENT_REQUIRED,
        );

        // 営業時間は7曜日ぶん
        $weekly = $s['business_hours']['weekly'] ?? null;
        if (is_array($weekly) && count($weekly) !== 7 && !in_array('business_hours.weekly', $missing, true)) {
            $missing[] = 'business_hours.weekly';
        }

        // 料金: 税区分 unknown / 税込総額なしは掲載できない（SSOT §3.3）
        foreach ((array)($s['menus'] ?? []) as $i => $menu) {
            if (!is_array($menu)) {
                $missing[] = 'menus';
                continue;
            }
            $type = $menu['price_type'] ?? 'fixed';
            if (in_array($type, ['fixed', 'from', 'free'], true) && !isset($menu['price_inc_tax'])) {
                $missing[] = 'menus[' . (int)$i . '].price_inc_tax';
            }
            if (($menu['tax_type'] ?? 'unknown') === 'unknown') {
                $missing[] = 'menus[' . (int)$i . '].tax_type';
            }
        }

        // 写真は最低8枚。権利確認が無いものは数えない（SSOT §3.10）
        $images = array_values(array_filter(
            (array)($s['image_metadata'] ?? []),
            static fn ($img): bool => is_array($img) && ($img['rights_confirmed'] ?? false) === true
        ));
        if (count($images) < self::MIN_IMAGES) {
            $missing[] = 'image_metadata.min_' . self::MIN_IMAGES;
        }
        foreach ($images as $i => $img) {
            $role = $img['role'] ?? 'other';
            if (in_array($role, ['staff', 'owner', 'treatment_scene'], true) && ($img['person_consent'] ?? false) !== true) {
                $missing[] = 'image_metadata[' . (int)$i . '].person_consent';
            }
        }

        // 掲載するスタッフは本人同意が必須（SSOT §3.4）
        foreach ((array)($s['staff'] ?? []) as $i => $st) {
            if (is_array($st) && ($st['published'] ?? false) === true && ($st['consent_agreed'] ?? false) !== true) {
                $missing[] = 'staff[' . (int)$i . '].consent_agreed';
            }
        }

        // 法的確認は13件すべて true（SSOT §3.11）
        $confirmations = (array)($s['rights']['confirmations'] ?? []);
        $agreed        = count(array_filter(
            $confirmations,
            static fn ($c): bool => is_array($c) && ($c['agreed'] ?? false) === true
        ));
        if ($agreed !== self::RIGHTS_CONFIRMATIONS) {
            $missing[] = 'rights.confirmations.all_agreed';
        }

        $fieldCount = 0;
        foreach ($s as $section) {
            $fieldCount += is_array($section) ? count($section) : 0;
        }

        return ['field_count' => $fieldCount, 'missing' => array_values(array_unique($missing))];
    }

    /**
     * 最終提出（SSOT v1.3 §6.4）。
     *
     * 冪等化は4層で守る:
     *   1. 画面のボタン無効化（ここではない）
     *   2. **submission_id**  … 同じ要求の再送を、同じ結果で返す
     *   3. status            … 別要求としての二重提出を弾く
     *   4. DB の部分一意索引  … 同時送信の競合を最後に止める
     *
     * $onCommit は「成功したときにだけ、同じトランザクションの中で実行したい処理」
     * （案件を submitted へ遷移させる）。履歴だけ残って状態が変わらない中間状態を作らない。
     *
     * @param callable():void|null $onCommit
     * @return array{ok:bool,error?:string,already?:bool,missing?:list<string>,missing_count?:int}
     */
    public function submit(int $caseId, string $currentStatus, string $submissionId, ?callable $onCommit = null): array
    {
        if (!self::isValidSubmissionId($submissionId)) {
            // 値そのものは記録も返却もしない（SSOT §10.7）
            return ['ok' => false, 'error' => 'bad_request'];
        }
        $submissionId = strtolower($submissionId);

        // 層2: 同じ submission_id を先に処理済みなら、そのときの結果を返すだけ
        $recorded = $this->findBySubmissionId($caseId, $submissionId);
        if ($recorded !== null) {
            return $this->replay($caseId, $recorded);
        }

        // 層3: 別要求としての二重提出
        if (in_array($currentStatus, ['submitted', 'reviewed'], true)) {
            return ['ok' => false, 'error' => 'already_submitted'];
        }
        if (!in_array($currentStatus, CaseService::EDITABLE, true)) {
            return ['ok' => false, 'error' => 'not_editable'];
        }

        $result  = $this->evaluate($caseId);
        $missing = $result['missing'];
        $schemaV = $this->get($caseId)['schema_version'];

        try {
            return $this->db->transaction(function () use ($caseId, $schemaV, $result, $missing, $submissionId, $onCommit): array {
                if ($missing !== []) {
                    $this->recordHistory(
                        $caseId, 'submitted', $schemaV, 'validation_error',
                        $result['field_count'], count($missing), $submissionId
                    );
                    $this->audit->record($caseId, 'submitted', 'validation_error');

                    return ['ok' => false, 'error' => 'incomplete', 'missing' => $missing, 'missing_count' => count($missing)];
                }

                $this->recordHistory($caseId, 'submitted', $schemaV, 'ok', $result['field_count'], 0, $submissionId);
                $this->audit->record($caseId, 'submitted', 'ok');

                if ($onCommit !== null) {
                    $onCommit();
                }

                return ['ok' => true, 'already' => false];
            });
        } catch (\PDOException $e) {
            // 層4: 同時送信で一意索引に負けた。例外を外へ出さず、勝った側の結果を返す
            if (!self::isUniqueViolation($e)) {
                throw $e;
            }
            $recorded = $this->findBySubmissionId($caseId, $submissionId);

            return $recorded === null
                ? ['ok' => false, 'error' => 'conflict']
                : $this->replay($caseId, $recorded);
        }
    }

    /**
     * 記録済みの提出要求を、副作用なしで返し直す（履歴も監査も増やさない）。
     * @param array<string,mixed> $recorded
     * @return array{ok:bool,error?:string,already?:bool,missing?:list<string>,missing_count?:int}
     */
    private function replay(int $caseId, array $recorded): array
    {
        if ((string)$recorded['result_code'] === 'ok') {
            return ['ok' => true, 'already' => true];
        }

        // 検証エラーだった要求の再送。現時点の不足を返す（記録は増やさない）
        $missing = $this->evaluate($caseId)['missing'];

        return ['ok' => false, 'error' => 'incomplete', 'missing' => $missing, 'missing_count' => count($missing)];
    }

    /** UUID v4 のみ（SSOT v1.3 §2.4-1）。大文字小文字は問わないが、保存は小文字へ揃える */
    public static function isValidSubmissionId(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }

    /** @return array<string,mixed>|null */
    private function findBySubmissionId(int $caseId, string $submissionId): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM intake_submission_history
              WHERE intake_case_id = :id AND submission_id = :sid
              LIMIT 1'
        );
        $stmt->execute([':id' => $caseId, ':sid' => $submissionId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private static function isUniqueViolation(\PDOException $e): bool
    {
        return ($e->getCode() === '23000' || ($e->errorInfo[1] ?? null) === 19)
            && stripos($e->getMessage(), 'unique') !== false;
    }

    public function recordHistory(
        int $caseId,
        string $eventType,
        int $schemaVersion,
        string $resultCode,
        ?int $fieldCount = null,
        ?int $missingCount = null,
        ?string $submissionId = null,
    ): void {
        $this->db->pdo()->prepare(
            'INSERT INTO intake_submission_history
                (intake_case_id, event_type, schema_version, submitted_at, result_code,
                 field_count, missing_count, submission_id)
             VALUES (:case_id, :event_type, :schema_version, :submitted_at, :result_code,
                 :field_count, :missing_count, :submission_id)'
        )->execute([
            ':case_id'        => $caseId,
            ':event_type'     => $eventType,
            ':schema_version' => $schemaVersion,
            ':submitted_at'   => $this->clock->iso(),
            ':result_code'    => $resultCode,
            ':field_count'    => $fieldCount,
            ':missing_count'  => $missingCount,
            ':submission_id'  => $submissionId,
        ]);
    }

    /**
     * 提出履歴の明細（管理画面の表示用）。
     * ★`submission_id` を返さない（操作の識別子であり、画面にもログにも出さない）。
     * @return list<array<string,mixed>>
     */
    public function historyRows(int $caseId, int $limit = 50): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT event_type, submitted_at, result_code, field_count, missing_count
               FROM intake_submission_history
              WHERE intake_case_id = :id
              ORDER BY submitted_at DESC, id DESC
              LIMIT :limit'
        );
        $stmt->bindValue(':id', $caseId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function historyCount(int $caseId): int
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM intake_submission_history WHERE intake_case_id = :id'
        );
        $stmt->execute([':id' => $caseId]);

        return (int)$stmt->fetchColumn();
    }
}
