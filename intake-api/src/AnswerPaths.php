<?php
/**
 * HP Intake API — §3 の正式なデータパス一覧（SSOT v1.5 §2.8-1）。
 *
 * 修正依頼（intake_revision_requests.requested_paths_json）が指してよいのは、
 * **この一覧に載っているものだけ**である。未知のパスを含む要求は丸ごと拒否する。
 *
 * 形は2種類:
 *   'basic'             … 分類そのもの（その分類ぜんぶを見直してほしい）
 *   'basic.legal_name'  … 分類 + 項目
 *
 * ★この一覧は画面側の定義（public/assets/lib/schema.js）から機械的に生成した。
 *   両者が食い違っていないことは tests/test-revision.php で検査している。
 *   **手で書き換えない。** §3 を変えたときは schema.js を直してから作り直す。
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

final class AnswerPaths
{
    /** @var list<string> */
    public const ALL = [
        'basic', 'basic.legal_name', 'basic.display_name', 'basic.operator_name',
        'basic.corporate_name', 'basic.postal_code', 'basic.address', 'basic.address_visibility',
        'basic.public_phone', 'basic.internal_contact.phone', 'basic.internal_contact.email',
        'basic.access_text', 'basic.parking', 'basic.service_area', 'basic.description',
        'basic.opened_year', 'basic.payment_methods', 'basic.booking_methods', 'basic.booking_note',
        'business_hours', 'business_hours.weekly', 'business_hours.closed_note',
        'business_hours.irregular_notice', 'business_hours.note', 'menus', 'menus.name',
        'menus.category', 'menus.price_type', 'menus.price_inc_tax', 'menus.price_ex_tax',
        'menus.tax_type', 'menus.duration_minutes', 'menus.description', 'menus.note', 'menus.target',
        'menus.published', 'menus.bookable', 'menus.first_time_only', 'menus.limited_period',
        'menus.period_start', 'menus.period_end', 'menus.cancel_policy', 'staff', 'staff.display_name',
        'staff.real_name', 'staff.role', 'staff.career', 'staff.qualifications', 'staff.specialty',
        'staff.menu_names', 'staff.bio', 'staff.photo_ref', 'staff.nominatable', 'staff.published',
        'staff.consent_agreed', 'staff.consent_date', 'promotion', 'promotion.strengths',
        'promotion.customer_profile', 'promotion.problems', 'promotion.recommended_menus',
        'promotion.difference', 'promotion.concept', 'promotion.owner_message',
        'promotion.founding_story', 'promotion.service_values', 'promotion.exclusions',
        'promotion.forbidden_expressions', 'promotion.competitors', 'promotion.achievements',
        'promotion.achievements_evidence', 'promotion.testimonials',
        'promotion.testimonials_permitted', 'promotion.testimonials_permitted_date', 'design',
        'design.template', 'design.preferred_colors', 'design.avoid_colors', 'design.tone',
        'design.reference_sites', 'design.reference_likes', 'design.avoid_design', 'design.logo',
        'design.font_preference', 'design.emphasis', 'design.hero_message', 'web_links',
        'web_links.current_site', 'web_links.existing_domain', 'web_links.desired_domain',
        'web_links.external_booking_url', 'web_links.line_add_url', 'web_links.instagram',
        'web_links.other_sns', 'web_links.google_business', 'web_links.contact_methods',
        'web_links.public_email', 'web_links.map_display', 'web_links.current_server',
        'web_links.domain_registrar', 'web_links.existing_mail', 'contact_form',
        'contact_form.enabled', 'contact_form.topics', 'contact_form.internal_to', 'privacy',
        'privacy.collected_data', 'privacy.purpose', 'privacy.retention', 'privacy.third_party',
        'privacy.contact_window', 'privacy.marketing_use', 'image_metadata',
        'image_metadata.file_name', 'image_metadata.role', 'image_metadata.provider',
        'image_metadata.rights_confirmed', 'image_metadata.person_consent',
        'image_metadata.person_consent_date', 'image_metadata.alt', 'image_metadata.published',
        'image_metadata.placement', 'image_metadata.expires_on', 'image_metadata.ai_generated',
        'image_metadata.note', 'rights', 'rights.confirmations', 'rights.agreed_by', 'rights.note',
    ];

    public static function isValid(string $path): bool
    {
        return in_array($path, self::ALL, true);
    }

    /**
     * 要求されたパスを検査して正規化する。
     *
     * ★1つでも未知のパスがあれば**丸ごと拒否**する（一部だけ受け入れない）。
     * ★重複は取り除く。順序は入力順を保つ（担当者が並べた意図を壊さない）。
     *
     * @param mixed $paths
     * @return array{ok:bool,paths?:list<string>,error?:string}
     */
    public static function normalize(mixed $paths): array
    {
        if (!is_array($paths) || $paths === []) {
            return ['ok' => false, 'error' => 'empty'];
        }
        if (count($paths) > count(self::ALL)) {
            return ['ok' => false, 'error' => 'too_many'];
        }

        $out  = [];
        $seen = [];
        foreach ($paths as $path) {
            if (!is_string($path) || !self::isValid($path)) {
                // ★どのパスが不正だったかは返さない（内部の一覧を推測させない）
                return ['ok' => false, 'error' => 'unknown_path'];
            }
            if (isset($seen[$path])) {
                continue;
            }
            $seen[$path] = true;
            $out[]       = $path;
        }

        return ['ok' => true, 'paths' => $out];
    }
}
