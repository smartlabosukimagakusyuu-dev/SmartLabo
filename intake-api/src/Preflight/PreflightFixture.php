<?php
/**
 * HP Intake API — preflight 通し確認で使う架空データ
 * （HP-ONBOARDING-4H-4 / SSOT §9.10）。
 *
 * ★実在の店舗・個人・住所・電話・メールを一切含まない。**すべて架空**である。
 * ★メールは `example.invalid` のみ。実送信はしない（通知は NullNotifier）。
 * ★内容は `dev/walkthrough-answers.php` と同一である
 *   （`tests/test-preflight-cli.php` が一致を固定している）。
 *   dev/ は本番へ配置しないため、本番でも使えるようここへ置く。
 */
declare(strict_types=1);

namespace SmartLabo\Intake\Preflight;

final class PreflightFixture
{
    /** 架空の店舗表示名（実在しない） */
    public const SHOP = "架空サロン ハルカゼ";

    /** 架空の Drive フォルダURL（実在しない） */
    public const DRIVE_URL = "https://drive.google.com/drive/folders/FAKE-PREFLIGHT-000000";

    /** 架空の共有先メール（example.invalid のみ） */
    public const DRIVE_EMAIL = "materials@example.invalid";

    /** SSOT §3 の必須をすべて満たす、架空の回答一式 */
    public static function answers(): array
    {
        return array (
          'basic' => 
          array (
            'legal_name' => 'ヘアサロン ハルカゼ',
            'operator_name' => 'ハルカゼ',
            'postal_code' => '000-0000',
            'address' => '架空県架空市架空町1-2-3',
            'access_text' => '架空駅から徒歩3分',
            'description' => '架空の店舗紹介文です。',
            'payment_methods' => 
            array (
              0 => 'cash',
            ),
            'booking_methods' => 
            array (
              0 => 'web',
            ),
            'internal_contact' => 
            array (
              'phone' => '03-0000-0000',
              'email' => 'internal@example.invalid',
            ),
            'address_visibility' => 'full',
            'parking' => 
            array (
              'type' => 'own',
              'note' => '2台',
            ),
          ),
          'business_hours' => 
          array (
            'weekly' => 
            array (
              0 => 
              array (
                'day' => 0,
                'closed' => false,
                'open' => '09:00',
                'close' => '18:00',
              ),
              1 => 
              array (
                'day' => 1,
                'closed' => true,
                'open' => NULL,
                'close' => NULL,
              ),
              2 => 
              array (
                'day' => 2,
                'closed' => false,
                'open' => '09:00',
                'close' => '18:00',
              ),
              3 => 
              array (
                'day' => 3,
                'closed' => false,
                'open' => '09:00',
                'close' => '18:00',
              ),
              4 => 
              array (
                'day' => 4,
                'closed' => false,
                'open' => '09:00',
                'close' => '18:00',
              ),
              5 => 
              array (
                'day' => 5,
                'closed' => false,
                'open' => '09:00',
                'close' => '18:00',
              ),
              6 => 
              array (
                'day' => 6,
                'closed' => false,
                'open' => '09:00',
                'close' => '18:00',
              ),
            ),
            'closed_note' => '毎週月曜',
            'irregular_notice' => 'none',
          ),
          'menus' => 
          array (
            0 => 
            array (
              'name' => 'カット',
              'price_type' => 'fixed',
              'price_inc_tax' => 5500,
              'tax_type' => 'inc',
              'published' => true,
              'bookable' => true,
              'first_time_only' => false,
              'limited_period' => false,
            ),
          ),
          'staff' => 
          array (
          ),
          'promotion' => 
          array (
            'strengths' => 
            array (
              0 => '架空の強み',
            ),
            'customer_profile' => '架空の顧客層',
            'problems' => '架空のお悩み',
            'recommended_menus' => 
            array (
              0 => 'カット',
            ),
            'concept' => '架空のコンセプト',
            'exclusions' => 'なし',
            'forbidden_expressions' => 'なし',
          ),
          'design' => 
          array (
            'template' => 'beauty',
            'tone' => 
            array (
              0 => 'シンプル',
            ),
            'hero_message' => '架空のメッセージ',
            'logo' => 'none',
            'emphasis' => 'photo',
          ),
          'web_links' => 
          array (
            'contact_methods' => 
            array (
              0 => 'phone',
            ),
            'map_display' => 'show',
          ),
          'contact_form' => 
          array (
            'enabled' => false,
          ),
          'privacy' => 
          array (
            'collected_data' => 
            array (
              0 => 'name',
            ),
            'purpose' => '架空の目的',
            'retention' => '1年',
            'third_party' => 'none',
            'contact_window' => '架空の窓口',
            'marketing_use' => 'no',
          ),
          'image_metadata' => 
          array (
            0 => 
            array (
              'file_name' => 'photo-0.jpg',
              'role' => 'exterior',
              'provider' => 'shop',
              'rights_confirmed' => true,
              'published' => true,
              'ai_generated' => false,
            ),
            1 => 
            array (
              'file_name' => 'photo-1.jpg',
              'role' => 'interior',
              'provider' => 'shop',
              'rights_confirmed' => true,
              'published' => true,
              'ai_generated' => false,
            ),
            2 => 
            array (
              'file_name' => 'photo-2.jpg',
              'role' => 'interior',
              'provider' => 'shop',
              'rights_confirmed' => true,
              'published' => true,
              'ai_generated' => false,
            ),
            3 => 
            array (
              'file_name' => 'photo-3.jpg',
              'role' => 'interior',
              'provider' => 'shop',
              'rights_confirmed' => true,
              'published' => true,
              'ai_generated' => false,
            ),
            4 => 
            array (
              'file_name' => 'photo-4.jpg',
              'role' => 'interior',
              'provider' => 'shop',
              'rights_confirmed' => true,
              'published' => true,
              'ai_generated' => false,
            ),
            5 => 
            array (
              'file_name' => 'photo-5.jpg',
              'role' => 'interior',
              'provider' => 'shop',
              'rights_confirmed' => true,
              'published' => true,
              'ai_generated' => false,
            ),
            6 => 
            array (
              'file_name' => 'photo-6.jpg',
              'role' => 'interior',
              'provider' => 'shop',
              'rights_confirmed' => true,
              'published' => true,
              'ai_generated' => false,
            ),
            7 => 
            array (
              'file_name' => 'photo-7.jpg',
              'role' => 'interior',
              'provider' => 'shop',
              'rights_confirmed' => true,
              'published' => true,
              'ai_generated' => false,
            ),
          ),
          'rights' => 
          array (
            'confirmations' => 
            array (
              0 => 
              array (
                'code' => 'L-01',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              1 => 
              array (
                'code' => 'L-02',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              2 => 
              array (
                'code' => 'L-03',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              3 => 
              array (
                'code' => 'L-04',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              4 => 
              array (
                'code' => 'L-05',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              5 => 
              array (
                'code' => 'L-06',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              6 => 
              array (
                'code' => 'L-07',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              7 => 
              array (
                'code' => 'L-08',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              8 => 
              array (
                'code' => 'L-09',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              9 => 
              array (
                'code' => 'L-10',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              10 => 
              array (
                'code' => 'L-11',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              11 => 
              array (
                'code' => 'L-12',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
              12 => 
              array (
                'code' => 'L-13',
                'agreed' => true,
                'agreed_at' => '2026-08-27T00:00:00Z',
              ),
            ),
            'agreed_by' => '架空 担当者',
          ),
        );
    }
}
