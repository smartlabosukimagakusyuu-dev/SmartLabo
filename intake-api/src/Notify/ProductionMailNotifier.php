<?php
/**
 * HP Intake API — 提出通知の実送信（HP-ONBOARDING-4H-R0 / SSOT v1.12 §9.11）。
 *
 * ★**`mail()` を呼んでよいのはこのファイルだけ**である。
 *   `tests/test-security-static.php` が、これ以外の src ファイルでの
 *   `mail()` 使用を失敗させる（allowlist はこの1件のみ）。
 *
 * ★宛先・差出人は `Config` が受け取る時点で検査済みである
 *   （制御文字・空白・カンマ・セミコロン・山括弧・引用符・バックスラッシュを拒否）。
 *   それでも**ここでもう一度検査する**。設定を経由しない呼び出しを作られても
 *   ヘッダー注入を通さないためである。
 *
 * ★ヘッダーは**固定の allowlist**。呼び出し側から追加できない。
 * ★text/plain・UTF-8。HTML メールにしない。
 * ★本文・宛先・件名を**ログにも監査にも書かない**。
 * ★例外を外へ出さない。失敗は false で返し、提出そのものは成功のまま維持する。
 * ★自動再送しない（§9.11-6）。必要になったら別工程で設計する。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Notify;

final class ProductionMailNotifier implements Notifier
{
    public function __construct(
        private readonly string $recipient,
        private readonly string $from,
    ) {
    }

    public function enabled(): bool
    {
        return self::addressAcceptable($this->recipient) && self::addressAcceptable($this->from);
    }

    public function notifySubmitted(SubmissionNotice $notice): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $subject = $notice->subject();
        $body    = $notice->body();

        // ★件名・本文に改行由来のヘッダーを混ぜられないことを最後に確かめる。
        //   件名は1行だけ。本文の改行は LF のみに揃える。
        if (self::hasHeaderBreak($subject)) {
            return false;
        }
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        // ★ヘッダーは固定。呼び出し側から追加させない
        $headers = implode("\r\n", [
            'From: ' . $this->from,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Auto-Response-Suppress: All',
            'Auto-Submitted: auto-generated',
        ]);

        try {
            // ★第5引数（追加パラメータ）を使わない。sendmail へ任意の引数を渡さない
            return mail($this->recipient, self::encodeSubject($subject), $body, $headers) === true;
        } catch (\Throwable $e) {
            // ★例外の内容を外へ出さない。提出は成功のまま維持する
            return false;
        }
    }

    /**
     * 受け付けてよいアドレスか。★`Config` と同じ規則を、送信の直前にもう一度見る。
     */
    public static function addressAcceptable(string $address): bool
    {
        if ($address === '' || strlen($address) > 254) {
            return false;
        }
        for ($i = 0, $n = strlen($address); $i < $n; ++$i) {
            $code = ord($address[$i]);
            if ($code < 0x21 || $code === 0x7f) {
                return false; // 制御文字（CR/LF/TAB）・空白・DEL
            }
        }
        if (str_contains($address, chr(92))) {
            return false; // バックスラッシュ
        }
        foreach ([',', ';', '<', '>', '"', "'", '(', ')', '[', ']', ':'] as $bad) {
            if (str_contains($address, $bad)) {
                return false; // 複数宛先・表示名・引用符の入口
            }
        }

        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** ヘッダーを割れる文字（CR / LF / NUL）を含むか */
    public static function hasHeaderBreak(string $value): bool
    {
        return str_contains($value, "\r") || str_contains($value, "\n") || str_contains($value, "\0");
    }

    /**
     * 件名を MIME エンコードする。
     * ★日本語を含むため、生のまま渡さない。encoded-word なら改行も混ざらない。
     */
    public static function encodeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }
}
