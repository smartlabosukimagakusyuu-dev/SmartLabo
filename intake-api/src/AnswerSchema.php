<?php
/**
 * HP Intake API — 回答 JSON の正式構造（**生成物**）。
 *
 * ★このファイルは `dev/generate-answer-schema.mjs` が
 *   `public/assets/lib/schema.js` から機械生成している。**手で書き換えない。**
 *   §3 を変えるときは schema.js を直してから作り直す:
 *
 *     node intake-api/dev/generate-answer-schema.mjs
 *
 * ★同じ定義を PHP と JavaScript へ二重に手書きしないための仕組みである。
 *   生成し直しても差分が出ないこと（冪等であること）をテストで固定している。
 *
 * 形の種類（SSOT v1.8 §3.0-9）:
 *   scalar  … 文字列・数値・真偽・null。**配列やオブジェクトは受け付けない**
 *   bool    … 真偽 または null
 *   list    … scalar だけを並べた配列
 *   object  … 決まったキーだけを持つオブジェクト
 *   objects … object を並べた配列
 */
declare(strict_types=1);

namespace SmartLabo\Intake;

final class AnswerSchema
{
    /** 分類名（intake_answers の JSON 列と1対1） */
    public const SECTIONS = [
        'basic', 'business_hours', 'menus',
        'staff', 'promotion', 'design',
        'web_links', 'contact_form', 'privacy',
        'image_metadata', 'rights',
    ];

    /** §3 の正式なデータパス（分類そのもの ＋ 分類.項目） */
    public const PATHS = [
        'basic', 'basic.legal_name', 'basic.display_name',
        'basic.operator_name', 'basic.corporate_name', 'basic.postal_code',
        'basic.address', 'basic.address_visibility', 'basic.public_phone',
        'basic.internal_contact.phone', 'basic.internal_contact.email', 'basic.access_text',
        'basic.parking', 'basic.service_area', 'basic.description',
        'basic.opened_year', 'basic.payment_methods', 'basic.booking_methods',
        'basic.booking_note', 'business_hours', 'business_hours.weekly',
        'business_hours.closed_note', 'business_hours.irregular_notice', 'business_hours.note',
        'menus', 'menus.name', 'menus.category',
        'menus.price_type', 'menus.price_inc_tax', 'menus.price_ex_tax',
        'menus.tax_type', 'menus.duration_minutes', 'menus.description',
        'menus.note', 'menus.target', 'menus.published',
        'menus.bookable', 'menus.first_time_only', 'menus.limited_period',
        'menus.period_start', 'menus.period_end', 'menus.cancel_policy',
        'staff', 'staff.display_name', 'staff.real_name',
        'staff.role', 'staff.career', 'staff.qualifications',
        'staff.specialty', 'staff.menu_names', 'staff.bio',
        'staff.photo_ref', 'staff.nominatable', 'staff.published',
        'staff.consent_agreed', 'staff.consent_date', 'promotion',
        'promotion.strengths', 'promotion.customer_profile', 'promotion.problems',
        'promotion.recommended_menus', 'promotion.difference', 'promotion.concept',
        'promotion.owner_message', 'promotion.founding_story', 'promotion.service_values',
        'promotion.exclusions', 'promotion.forbidden_expressions', 'promotion.competitors',
        'promotion.achievements', 'promotion.achievements_evidence', 'promotion.testimonials',
        'promotion.testimonials_permitted', 'promotion.testimonials_permitted_date', 'design',
        'design.template', 'design.preferred_colors', 'design.avoid_colors',
        'design.tone', 'design.reference_sites', 'design.reference_likes',
        'design.avoid_design', 'design.logo', 'design.font_preference',
        'design.emphasis', 'design.hero_message', 'web_links',
        'web_links.current_site', 'web_links.existing_domain', 'web_links.desired_domain',
        'web_links.external_booking_url', 'web_links.line_add_url', 'web_links.instagram',
        'web_links.other_sns', 'web_links.google_business', 'web_links.contact_methods',
        'web_links.public_email', 'web_links.map_display', 'web_links.current_server',
        'web_links.domain_registrar', 'web_links.existing_mail', 'contact_form',
        'contact_form.enabled', 'contact_form.topics', 'contact_form.internal_to',
        'privacy', 'privacy.collected_data', 'privacy.purpose',
        'privacy.retention', 'privacy.third_party', 'privacy.contact_window',
        'privacy.marketing_use', 'image_metadata', 'image_metadata.file_name',
        'image_metadata.role', 'image_metadata.provider', 'image_metadata.rights_confirmed',
        'image_metadata.person_consent', 'image_metadata.person_consent_date', 'image_metadata.alt',
        'image_metadata.published', 'image_metadata.placement', 'image_metadata.expires_on',
        'image_metadata.ai_generated', 'image_metadata.note', 'rights',
        'rights.confirmations', 'rights.agreed_by', 'rights.note',
    ];

    /**
     * 分類ごとの構造。保存要求の検査と、読み出し時の絞り込みに使う。
     * @var array<string,array<string,mixed>>
     */
    public const STRUCTURE = [
        'basic' => [
            'type' => 'object',
            'fields' => [
                'legal_name' => [
                    'type' => 'scalar',
                ],
                'display_name' => [
                    'type' => 'scalar',
                ],
                'operator_name' => [
                    'type' => 'scalar',
                ],
                'corporate_name' => [
                    'type' => 'scalar',
                ],
                'postal_code' => [
                    'type' => 'scalar',
                ],
                'address' => [
                    'type' => 'scalar',
                ],
                'address_visibility' => [
                    'type' => 'scalar',
                ],
                'public_phone' => [
                    'type' => 'scalar',
                ],
                'internal_contact' => [
                    'type' => 'object',
                    'fields' => [
                        'phone' => [
                            'type' => 'scalar',
                        ],
                        'email' => [
                            'type' => 'scalar',
                        ],
                    ],
                ],
                'access_text' => [
                    'type' => 'scalar',
                ],
                'parking' => [
                    'type' => 'object',
                    'fields' => [
                        'type' => [
                            'type' => 'scalar',
                        ],
                        'note' => [
                            'type' => 'scalar',
                        ],
                    ],
                ],
                'service_area' => [
                    'type' => 'scalar',
                ],
                'description' => [
                    'type' => 'scalar',
                ],
                'opened_year' => [
                    'type' => 'scalar',
                ],
                'payment_methods' => [
                    'type' => 'list',
                ],
                'booking_methods' => [
                    'type' => 'list',
                ],
                'booking_note' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'business_hours' => [
            'type' => 'object',
            'fields' => [
                'weekly' => [
                    'type' => 'objects',
                    'item' => [
                        'day' => [
                            'type' => 'scalar',
                        ],
                        'closed' => [
                            'type' => 'scalar',
                        ],
                        'open' => [
                            'type' => 'scalar',
                        ],
                        'close' => [
                            'type' => 'scalar',
                        ],
                    ],
                ],
                'closed_note' => [
                    'type' => 'scalar',
                ],
                'irregular_notice' => [
                    'type' => 'scalar',
                ],
                'note' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'menus' => [
            'type' => 'objects',
            'item' => [
                'name' => [
                    'type' => 'scalar',
                ],
                'category' => [
                    'type' => 'scalar',
                ],
                'price_type' => [
                    'type' => 'scalar',
                ],
                'price_inc_tax' => [
                    'type' => 'scalar',
                ],
                'price_ex_tax' => [
                    'type' => 'scalar',
                ],
                'tax_type' => [
                    'type' => 'scalar',
                ],
                'duration_minutes' => [
                    'type' => 'scalar',
                ],
                'description' => [
                    'type' => 'scalar',
                ],
                'note' => [
                    'type' => 'scalar',
                ],
                'target' => [
                    'type' => 'scalar',
                ],
                'published' => [
                    'type' => 'bool',
                ],
                'bookable' => [
                    'type' => 'bool',
                ],
                'first_time_only' => [
                    'type' => 'bool',
                ],
                'limited_period' => [
                    'type' => 'bool',
                ],
                'period_start' => [
                    'type' => 'scalar',
                ],
                'period_end' => [
                    'type' => 'scalar',
                ],
                'cancel_policy' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'staff' => [
            'type' => 'objects',
            'item' => [
                'display_name' => [
                    'type' => 'scalar',
                ],
                'real_name' => [
                    'type' => 'scalar',
                ],
                'role' => [
                    'type' => 'scalar',
                ],
                'career' => [
                    'type' => 'scalar',
                ],
                'qualifications' => [
                    'type' => 'scalar',
                ],
                'specialty' => [
                    'type' => 'scalar',
                ],
                'menu_names' => [
                    'type' => 'list',
                ],
                'bio' => [
                    'type' => 'scalar',
                ],
                'photo_ref' => [
                    'type' => 'scalar',
                ],
                'nominatable' => [
                    'type' => 'bool',
                ],
                'published' => [
                    'type' => 'bool',
                ],
                'consent_agreed' => [
                    'type' => 'bool',
                ],
                'consent_date' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'promotion' => [
            'type' => 'object',
            'fields' => [
                'strengths' => [
                    'type' => 'list',
                ],
                'customer_profile' => [
                    'type' => 'scalar',
                ],
                'problems' => [
                    'type' => 'scalar',
                ],
                'recommended_menus' => [
                    'type' => 'list',
                ],
                'difference' => [
                    'type' => 'scalar',
                ],
                'concept' => [
                    'type' => 'scalar',
                ],
                'owner_message' => [
                    'type' => 'scalar',
                ],
                'founding_story' => [
                    'type' => 'scalar',
                ],
                'service_values' => [
                    'type' => 'scalar',
                ],
                'exclusions' => [
                    'type' => 'scalar',
                ],
                'forbidden_expressions' => [
                    'type' => 'scalar',
                ],
                'competitors' => [
                    'type' => 'scalar',
                ],
                'achievements' => [
                    'type' => 'scalar',
                ],
                'achievements_evidence' => [
                    'type' => 'scalar',
                ],
                'testimonials' => [
                    'type' => 'list',
                ],
                'testimonials_permitted' => [
                    'type' => 'bool',
                ],
                'testimonials_permitted_date' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'design' => [
            'type' => 'object',
            'fields' => [
                'template' => [
                    'type' => 'scalar',
                ],
                'preferred_colors' => [
                    'type' => 'scalar',
                ],
                'avoid_colors' => [
                    'type' => 'scalar',
                ],
                'tone' => [
                    'type' => 'list',
                ],
                'reference_sites' => [
                    'type' => 'list',
                ],
                'reference_likes' => [
                    'type' => 'scalar',
                ],
                'avoid_design' => [
                    'type' => 'scalar',
                ],
                'logo' => [
                    'type' => 'scalar',
                ],
                'font_preference' => [
                    'type' => 'scalar',
                ],
                'emphasis' => [
                    'type' => 'scalar',
                ],
                'hero_message' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'web_links' => [
            'type' => 'object',
            'fields' => [
                'current_site' => [
                    'type' => 'scalar',
                ],
                'existing_domain' => [
                    'type' => 'scalar',
                ],
                'desired_domain' => [
                    'type' => 'scalar',
                ],
                'external_booking_url' => [
                    'type' => 'scalar',
                ],
                'line_add_url' => [
                    'type' => 'scalar',
                ],
                'instagram' => [
                    'type' => 'scalar',
                ],
                'other_sns' => [
                    'type' => 'list',
                ],
                'google_business' => [
                    'type' => 'scalar',
                ],
                'contact_methods' => [
                    'type' => 'list',
                ],
                'public_email' => [
                    'type' => 'scalar',
                ],
                'map_display' => [
                    'type' => 'scalar',
                ],
                'current_server' => [
                    'type' => 'scalar',
                ],
                'domain_registrar' => [
                    'type' => 'scalar',
                ],
                'existing_mail' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'contact_form' => [
            'type' => 'object',
            'fields' => [
                'enabled' => [
                    'type' => 'bool',
                ],
                'topics' => [
                    'type' => 'list',
                ],
                'internal_to' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'privacy' => [
            'type' => 'object',
            'fields' => [
                'collected_data' => [
                    'type' => 'list',
                ],
                'purpose' => [
                    'type' => 'scalar',
                ],
                'retention' => [
                    'type' => 'scalar',
                ],
                'third_party' => [
                    'type' => 'scalar',
                ],
                'contact_window' => [
                    'type' => 'scalar',
                ],
                'marketing_use' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'image_metadata' => [
            'type' => 'objects',
            'item' => [
                'file_name' => [
                    'type' => 'scalar',
                ],
                'role' => [
                    'type' => 'scalar',
                ],
                'provider' => [
                    'type' => 'scalar',
                ],
                'rights_confirmed' => [
                    'type' => 'bool',
                ],
                'person_consent' => [
                    'type' => 'bool',
                ],
                'person_consent_date' => [
                    'type' => 'scalar',
                ],
                'alt' => [
                    'type' => 'scalar',
                ],
                'published' => [
                    'type' => 'bool',
                ],
                'placement' => [
                    'type' => 'scalar',
                ],
                'expires_on' => [
                    'type' => 'scalar',
                ],
                'ai_generated' => [
                    'type' => 'bool',
                ],
                'note' => [
                    'type' => 'scalar',
                ],
            ],
        ],
        'rights' => [
            'type' => 'object',
            'fields' => [
                'confirmations' => [
                    'type' => 'objects',
                    'item' => [
                        'code' => [
                            'type' => 'scalar',
                        ],
                        'agreed' => [
                            'type' => 'scalar',
                        ],
                        'agreed_at' => [
                            'type' => 'scalar',
                        ],
                    ],
                ],
                'agreed_by' => [
                    'type' => 'scalar',
                ],
                'note' => [
                    'type' => 'scalar',
                ],
            ],
        ],
    ];
}
