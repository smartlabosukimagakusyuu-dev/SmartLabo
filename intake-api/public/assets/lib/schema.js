/**
 * 入力項目の定義（SSOT v1.3 §3 の 11分類）。
 *
 * ★ここは SSOT §3 の表をそのまま写したものである。
 *   項目・型・上限・語彙を**推測で足したり削ったりしない**。
 *   サーバー側の検証（AnswerService）と食い違ったら、サーバーを正とする。
 *
 * ★画面文言に「HP Intake」「token」「session」等の内部用語を使わない。
 */

/** 入力欄の種類 */
export const KIND = {
  TEXT: 'text',
  TEXTAREA: 'textarea',
  NUMBER: 'number',
  SELECT: 'select',
  CHECKBOX: 'checkbox',
  CHECKS: 'checks', // 複数選択（語彙固定）
  LIST: 'list', // 文字列の配列
  OBJECTS: 'objects', // 繰り返しグループ
  WEEKLY: 'weekly', // 営業時間（固定7要素）
  CONFIRMATIONS: 'confirmations', // 法的確認（固定13件）
  PARKING: 'parking', // 駐車場（object）
};

/** 法的確認 L-01〜L-13（仕様書 §14） */
export const CONFIRMATION_ITEMS = [
  ['L-01', '提供した写真は、当店が使用する権利を有しています'],
  ['L-02', '写真に写る人物から、ホームページ掲載の同意を得ています'],
  ['L-03', '掲載するスタッフ情報について、本人の同意を得ています'],
  ['L-04', '記載した料金は正確です'],
  ['L-05', '記載した料金の税込総額を確認しました'],
  ['L-06', '記載した資格・実績・数値には根拠があり、提示できます'],
  ['L-07', 'お客様の声を掲載する場合、本人から掲載許可を得ています'],
  ['L-08', '使用する商標・ロゴについて、利用する権限を有しています'],
  ['L-09', '効果・効能を保証する表現を含んでいません'],
  ['L-10', '第三者のサイトから文章・写真を無断転載していません'],
  ['L-11', '入力内容をホームページへ掲載することに同意します'],
  ['L-12', 'ホームページに掲載するプライバシーポリシーの内容を確認しました'],
  ['L-13', '公開前に当店の最終承認が必要であることを理解しました'],
];

export const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

/** 写真の最低枚数（SSOT §3.10 / AnswerService::MIN_IMAGES） */
export const MIN_IMAGES = 8;

const f = (path, label, kind, opts = {}) => ({ path, label, kind, ...opts });

/**
 * 11分類 ＋ 確認ステップ。
 * key は intake_answers の JSON 分類名と一致させる（サーバーが受け付ける唯一の名前）。
 */
export const STEPS = [
  {
    key: 'basic',
    title: '基本情報',
    lead: 'お店の名前や住所など、ホームページの土台になる情報です。',
    fields: [
      f('legal_name', '店舗の正式名称', KIND.TEXT, { max: 100, required: true }),
      f('display_name', 'ホームページに載せる店名', KIND.TEXT, {
        max: 60,
        hint: '空欄のときは正式名称をそのまま使います。',
      }),
      f('operator_name', '運営者名', KIND.TEXT, { max: 100, required: true }),
      f('corporate_name', '法人名', KIND.TEXT, { max: 100, hint: '法人の場合のみご記入ください。' }),
      f('postal_code', '郵便番号', KIND.TEXT, { max: 10, required: true, placeholder: '000-0000' }),
      f('address', '住所', KIND.TEXT, { max: 300, required: true }),
      f('address_visibility', '住所の公開範囲', KIND.SELECT, {
        required: true,
        options: [
          ['full', 'すべて公開する'],
          ['city', '市区町村まで公開する'],
          ['area', 'おおよその地域まで公開する'],
          ['hidden', '公開しない'],
        ],
        hint: '市区町村まで・地域まで・公開しない を選ぶと、地図は自動で非表示になります。',
      }),
      f('public_phone', 'ホームページに載せる電話番号', KIND.TEXT, { max: 20, inputType: 'tel' }),
      f('internal_contact.phone', 'ご連絡先の電話番号', KIND.TEXT, {
        max: 20,
        required: true,
        inputType: 'tel',
        internal: true,
        hint: '制作中のご連絡に使います。ホームページには載せません。',
      }),
      f('internal_contact.email', 'ご連絡先のメールアドレス', KIND.TEXT, {
        max: 254,
        required: true,
        inputType: 'email',
        internal: true,
        hint: '制作中のご連絡に使います。ホームページには載せません。',
      }),
      f('access_text', 'アクセス・道順', KIND.TEXTAREA, {
        max: 500,
        required: true,
        placeholder: '○○駅から徒歩3分',
      }),
      f('parking', '駐車場', KIND.PARKING, { required: true }),
      f('service_area', '対応エリア', KIND.TEXT, { max: 200 }),
      f('description', 'お店の紹介文', KIND.TEXTAREA, { max: 2000, required: true, rows: 6 }),
      f('opened_year', '開業年', KIND.NUMBER, { min: 1800, max: 2999 }),
      f('payment_methods', 'お支払い方法', KIND.LIST, {
        required: true,
        cap: 10,
        itemMax: 30,
        addLabel: 'お支払い方法を追加',
        placeholder: '現金',
      }),
      f('booking_methods', 'ご予約方法', KIND.LIST, {
        required: true,
        cap: 6,
        itemMax: 30,
        addLabel: 'ご予約方法を追加',
        placeholder: '電話',
      }),
      f('booking_note', 'ご予約についての補足', KIND.TEXTAREA, { max: 300 }),
    ],
  },
  {
    key: 'business_hours',
    title: '営業時間・定休日',
    lead: '曜日ごとの営業時間をご記入ください。',
    fields: [
      f('weekly', '営業時間', KIND.WEEKLY, { required: true }),
      f('closed_note', '定休日', KIND.TEXT, { max: 100, required: true, placeholder: '毎週月曜' }),
      f('irregular_notice', '不定休のお知らせ方法', KIND.SELECT, {
        required: true,
        options: [
          ['none', 'お知らせしない'],
          ['instagram', 'Instagram'],
          ['line', 'LINE'],
          ['phone', '電話'],
        ],
      }),
      f('note', '営業時間の補足', KIND.TEXTAREA, { max: 300, hint: '昼休みなどがあればご記入ください。' }),
    ],
  },
  {
    key: 'menus',
    title: 'メニュー・料金',
    lead: '掲載するメニューを1件以上ご登録ください。料金は税込の総額でお願いします。',
    kind: KIND.OBJECTS,
    cap: 60,
    min: 1,
    addLabel: 'メニューを追加',
    itemLabel: 'メニュー',
    fields: [
      f('name', 'メニュー名', KIND.TEXT, { max: 100, required: true }),
      f('category', '分類', KIND.TEXT, { max: 50 }),
      f('price_type', '料金の種類', KIND.SELECT, {
        required: true,
        options: [
          ['fixed', '定額'],
          ['from', '○円〜'],
          ['quote', '都度お見積り'],
          ['undecided', '未定'],
          ['free', '無料'],
        ],
      }),
      f('price_inc_tax', '税込料金（円）', KIND.NUMBER, {
        min: 0,
        max: 999999999,
        hint: '「定額」「○円〜」「無料」を選んだ場合は必ずご記入ください。',
      }),
      f('price_ex_tax', '税抜料金（円）', KIND.NUMBER, { min: 0, max: 999999999 }),
      f('tax_type', '税の表示', KIND.SELECT, {
        required: true,
        options: [
          ['unknown', '未確認'],
          ['inc', '税込'],
          ['ex', '税抜'],
        ],
        hint: '「未確認」のままだと、このメニューはホームページに掲載できません。',
      }),
      f('duration_minutes', '所要時間（分）', KIND.NUMBER, { min: 1, max: 9999 }),
      f('description', '説明', KIND.TEXTAREA, { max: 500 }),
      f('note', '補足', KIND.TEXTAREA, { max: 300 }),
      f('target', '対象のお客様', KIND.TEXT, { max: 100 }),
      f('published', 'ホームページに掲載する', KIND.CHECKBOX, { default: true }),
      f('bookable', 'ご予約を受け付ける', KIND.CHECKBOX, { default: true }),
      f('first_time_only', '初回のお客様限定', KIND.CHECKBOX, { default: false }),
      f('limited_period', '期間限定', KIND.CHECKBOX, { default: false }),
      f('period_start', '期間（開始）', KIND.TEXT, { inputType: 'date' }),
      f('period_end', '期間（終了）', KIND.TEXT, { inputType: 'date' }),
      f('cancel_policy', 'キャンセルについて', KIND.TEXTAREA, { max: 500 }),
    ],
  },
  {
    key: 'staff',
    title: 'スタッフ',
    lead: '掲載するスタッフがいる場合はご登録ください。掲載にはご本人の同意が必要です。',
    kind: KIND.OBJECTS,
    cap: 30,
    addLabel: 'スタッフを追加',
    itemLabel: 'スタッフ',
    fields: [
      f('display_name', '掲載するお名前', KIND.TEXT, { max: 40 }),
      f('real_name', '本名', KIND.TEXT, { max: 40, internal: true, hint: 'ホームページには載せません。' }),
      f('role', '役職', KIND.TEXT, { max: 40 }),
      f('career', '経歴', KIND.TEXTAREA, { max: 500 }),
      f('qualifications', '資格', KIND.TEXTAREA, { max: 200 }),
      f('specialty', '得意な施術', KIND.TEXT, { max: 100 }),
      f('menu_names', '担当メニュー', KIND.LIST, { cap: 20, itemMax: 100, addLabel: '担当メニューを追加' }),
      f('bio', '紹介文', KIND.TEXTAREA, { max: 500 }),
      f('photo_ref', '写真のファイル名', KIND.TEXT, {
        max: 120,
        internal: true,
        hint: '共有フォルダへ入れた写真のファイル名をご記入ください。',
      }),
      f('nominatable', 'ご指名を受け付ける', KIND.CHECKBOX, { default: false }),
      f('published', 'ホームページに掲載する', KIND.CHECKBOX, { default: false }),
      f('consent_agreed', 'ご本人の掲載同意を得ている', KIND.CHECKBOX, { default: false }),
      f('consent_date', '同意を得た日', KIND.TEXT, { inputType: 'date' }),
    ],
  },
  {
    key: 'promotion',
    title: 'お店の特徴・掲載内容',
    lead: 'ホームページで何を伝えたいかをご記入ください。',
    fields: [
      f('strengths', 'お店の強み', KIND.LIST, {
        required: true,
        cap: 3,
        itemMax: 60,
        addLabel: '強みを追加',
      }),
      f('customer_profile', 'どんなお客様が多いか', KIND.TEXTAREA, {
        max: 300,
        required: true,
        internal: true,
        hint: 'ホームページには載せません。書き方の参考にします。',
      }),
      f('problems', 'お客様のお悩み', KIND.TEXTAREA, { max: 500, required: true }),
      f('recommended_menus', 'おすすめメニュー', KIND.LIST, {
        required: true,
        cap: 3,
        itemMax: 100,
        addLabel: 'おすすめメニューを追加',
      }),
      f('difference', '他店との違い', KIND.TEXTAREA, { max: 500 }),
      f('concept', 'お店のコンセプト', KIND.TEXTAREA, { max: 500, required: true }),
      f('owner_message', 'オーナーからのメッセージ', KIND.TEXTAREA, { max: 1000 }),
      f('founding_story', '開業のきっかけ', KIND.TEXTAREA, { max: 1000 }),
      f('service_values', '大切にしていること', KIND.TEXTAREA, { max: 500 }),
      f('exclusions', '載せたくないこと', KIND.TEXTAREA, {
        max: 500,
        required: true,
        internal: true,
        hint: '特になければ「なし」とご記入ください。',
      }),
      f('forbidden_expressions', '使ってほしくない表現', KIND.TEXTAREA, {
        max: 300,
        required: true,
        internal: true,
        hint: '特になければ「なし」とご記入ください。',
      }),
      f('competitors', '気になる同業のお店', KIND.TEXTAREA, { max: 300, internal: true }),
      f('achievements', '実績', KIND.TEXTAREA, {
        max: 500,
        hint: '数値を載せる場合は、根拠を次の欄にご記入ください。',
      }),
      f('achievements_evidence', '実績の根拠', KIND.TEXTAREA, { max: 500, internal: true }),
      f('testimonials', 'お客様の声', KIND.LIST, {
        cap: 3,
        itemMax: 300,
        addLabel: 'お客様の声を追加',
        hint: '掲載にはご本人の許可が必要です。',
      }),
      f('testimonials_permitted', 'お客様の声の掲載許可を得ている', KIND.CHECKBOX, {
        default: false,
        internal: true,
      }),
      f('testimonials_permitted_date', '許可を得た日', KIND.TEXT, { inputType: 'date', internal: true }),
    ],
  },
  {
    key: 'design',
    title: 'デザインのご希望',
    lead: '雰囲気のご希望をお聞かせください。細かい調整は当社で行います。',
    fields: [
      f('template', 'お店の種類', KIND.SELECT, {
        required: true,
        options: [
          ['beauty', '美容・サロン'],
          ['wellness', '整体・リラクゼーション'],
          ['private', '個人・少人数のお店'],
        ],
      }),
      f('preferred_colors', '使いたい色', KIND.TEXT, { max: 100 }),
      f('avoid_colors', '避けたい色', KIND.TEXT, { max: 100 }),
      f('tone', '雰囲気', KIND.CHECKS, {
        required: true,
        cap: 3,
        hint: '3つまでお選びください。',
        options: [
          ['明るく清潔', '明るく清潔'],
          ['落ち着き', '落ち着き'],
          ['高級感', '高級感'],
          ['親しみやすい', '親しみやすい'],
          ['自然', '自然'],
          ['シンプル', 'シンプル'],
          ['かわいい', 'かわいい'],
          ['スタイリッシュ', 'スタイリッシュ'],
        ],
      }),
      f('reference_sites', '参考にしたいサイト', KIND.LIST, {
        cap: 3,
        itemMax: 500,
        addLabel: '参考サイトを追加',
        hint: '雰囲気の参考にするだけで、まねはいたしません。',
      }),
      f('reference_likes', '参考サイトのどこが良いか', KIND.TEXTAREA, { max: 300 }),
      f('avoid_design', '避けたいデザイン', KIND.TEXTAREA, { max: 300 }),
      f('logo', 'ロゴ', KIND.SELECT, {
        required: true,
        options: [
          ['none', 'ロゴはない'],
          ['data', 'ロゴのデータがある'],
          ['image', '画像のみある'],
        ],
      }),
      f('font_preference', '文字の雰囲気', KIND.SELECT, {
        options: [
          ['auto', 'おまかせ'],
          ['mincho', '明朝体'],
          ['gothic', 'ゴシック体'],
        ],
      }),
      f('emphasis', '大きく見せたいもの', KIND.SELECT, {
        required: true,
        options: [
          ['photo', '写真'],
          ['text', '文章'],
        ],
      }),
      f('hero_message', 'いちばん伝えたい一言', KIND.TEXTAREA, { max: 200, required: true, rows: 2 }),
    ],
  },
  {
    key: 'web_links',
    title: 'SNS・外部リンク',
    lead: 'お持ちのページがあればご記入ください。リンクは https:// から始まる形でお願いします。',
    fields: [
      f('current_site', '現在のホームページ', KIND.TEXT, { max: 500, internal: true }),
      f('existing_domain', 'お持ちのドメイン', KIND.TEXT, { max: 253 }),
      f('desired_domain', 'ご希望のドメイン', KIND.TEXT, { max: 253, internal: true }),
      f('external_booking_url', '予約サイトのURL', KIND.TEXT, { max: 500, inputType: 'url', https: true }),
      f('line_add_url', 'LINE友だち追加のURL', KIND.TEXT, { max: 500, inputType: 'url', https: true }),
      f('instagram', 'InstagramのURL', KIND.TEXT, { max: 500, inputType: 'url', https: true }),
      f('other_sns', 'そのほかのSNS', KIND.LIST, {
        cap: 3,
        itemMax: 500,
        addLabel: 'SNSを追加',
        https: true,
      }),
      f('google_business', 'Googleビジネスプロフィールの URL', KIND.TEXT, {
        max: 500,
        inputType: 'url',
        https: true,
      }),
      f('contact_methods', 'お問い合わせの受付方法', KIND.CHECKS, {
        required: true,
        cap: 4,
        options: [
          ['phone', '電話'],
          ['email', 'メール'],
          ['line', 'LINE'],
          ['form', 'お問い合わせフォーム'],
        ],
      }),
      f('public_email', 'ホームページに載せるメールアドレス', KIND.TEXT, {
        max: 254,
        inputType: 'email',
        hint: '受付方法で「メール」を選んだ場合は必ずご記入ください。',
      }),
      f('map_display', '地図の表示', KIND.SELECT, {
        required: true,
        options: [
          ['show', '表示する'],
          ['hide', '表示しない'],
        ],
        hint: '住所の公開範囲を絞った場合は「表示しない」になります。',
      }),
      f('current_server', '現在お使いのサーバー', KIND.TEXT, { max: 100, internal: true }),
      f('domain_registrar', 'ドメインの取得先', KIND.TEXT, { max: 100, internal: true }),
      f('existing_mail', '独自ドメインのメールの利用', KIND.SELECT, {
        internal: true,
        options: [
          ['', '選択しない'],
          ['yes', '使っている'],
          ['no', '使っていない'],
          ['unknown', 'わからない'],
        ],
      }),
    ],
  },
  {
    key: 'contact_form',
    title: 'お問い合わせフォーム',
    lead: 'ホームページにお問い合わせフォームを置くかどうかを決めます。',
    fields: [
      f('enabled', 'お問い合わせフォームを設置する', KIND.CHECKBOX, { default: false }),
      f('topics', 'お問い合わせの種類', KIND.LIST, {
        cap: 5,
        itemMax: 40,
        addLabel: 'お問い合わせの種類を追加',
        hint: '設置する場合は3つ以上をお願いします。',
      }),
      f('internal_to', 'お問い合わせの送信先メールアドレス', KIND.TEXT, {
        max: 254,
        inputType: 'email',
        internal: true,
        hint: 'ホームページには載りません。',
      }),
    ],
  },
  {
    key: 'privacy',
    title: 'プライバシーについて',
    lead: 'ホームページに載せるプライバシーポリシーの内容になります。',
    fields: [
      f('collected_data', 'お預かりする情報', KIND.LIST, {
        required: true,
        cap: 20,
        itemMax: 60,
        addLabel: 'お預かりする情報を追加',
        placeholder: 'お名前',
      }),
      f('purpose', '利用目的', KIND.TEXTAREA, { max: 500, required: true }),
      f('retention', '保存期間', KIND.TEXT, { max: 200, required: true }),
      f('third_party', '第三者への提供', KIND.SELECT, {
        required: true,
        options: [
          ['none', '提供しない'],
          ['yes', '提供する'],
        ],
        hint: '「提供する」場合は、利用目的の欄に具体的にご記入ください。',
      }),
      f('contact_window', 'お問い合わせ窓口', KIND.TEXT, {
        max: 200,
        required: true,
        hint: 'ホームページに載る窓口です。ご連絡先のメールアドレスとは別に指定できます。',
      }),
      f('marketing_use', '広告・宣伝への利用', KIND.SELECT, {
        required: true,
        options: [
          ['no', '利用しない'],
          ['yes', '利用する'],
        ],
      }),
    ],
  },
  {
    key: 'image_metadata',
    title: '写真・素材の情報',
    lead: '写真そのものは共有フォルダへお入れください。この画面ではファイル名と権利の確認だけを行います。',
    kind: KIND.OBJECTS,
    cap: 60,
    min: MIN_IMAGES,
    addLabel: '写真を追加',
    itemLabel: '写真',
    fields: [
      f('file_name', 'ファイル名', KIND.TEXT, { max: 120, required: true, placeholder: 'gaikan-01.jpg' }),
      f('role', '写真の種類', KIND.SELECT, {
        required: true,
        options: [
          ['exterior', '外観'],
          ['interior', '内観'],
          ['shampoo_wait', 'シャンプー台・待合'],
          ['style', 'スタイル'],
          ['staff', 'スタッフ'],
          ['product', '商品'],
          ['logo', 'ロゴ'],
          ['reception', '受付'],
          ['treatment_room', '施術室'],
          ['treatment_scene', '施術風景'],
          ['equipment', '機器'],
          ['locker', 'ロッカー'],
          ['owner', 'オーナー'],
          ['tools', '道具'],
          ['landmark', '目印'],
          ['other', 'そのほか'],
        ],
      }),
      f('provider', '写真の用意', KIND.SELECT, {
        required: true,
        options: [
          ['shop', 'お店で撮影'],
          ['photographer', 'カメラマンが撮影'],
          ['ai', 'AIで作成'],
          ['other', 'そのほか'],
        ],
      }),
      f('rights_confirmed', 'この写真を使う権利がある', KIND.CHECKBOX, {
        default: false,
        hint: 'チェックがない写真はホームページに掲載できません。',
      }),
      f('person_consent', '写っている人の掲載同意を得ている', KIND.CHECKBOX, { default: false }),
      f('person_consent_date', '同意を得た日', KIND.TEXT, { inputType: 'date' }),
      f('alt', '写真の説明', KIND.TEXT, { max: 120, hint: '空欄の場合は当社で用意します。' }),
      f('published', 'ホームページに掲載する', KIND.CHECKBOX, { default: true }),
      f('placement', '掲載したい場所', KIND.SELECT, {
        options: [
          ['auto', 'おまかせ'],
          ['hero', 'いちばん上'],
          ['section', '各セクション'],
        ],
      }),
      f('expires_on', '掲載をやめる日', KIND.TEXT, { inputType: 'date' }),
      f('ai_generated', 'AIで作成した画像', KIND.CHECKBOX, { default: false }),
      f('note', '補足', KIND.TEXT, { max: 200 }),
    ],
  },
  {
    key: 'rights',
    title: '素材の使用・権利の確認',
    lead: 'ホームページを公開するために、13項目すべてのご確認をお願いします。',
    fields: [
      f('confirmations', 'ご確認いただく内容', KIND.CONFIRMATIONS, { required: true }),
      f('agreed_by', 'ご確認いただいた方のお名前', KIND.TEXT, { max: 60, required: true, internal: true }),
      f('note', '補足', KIND.TEXTAREA, { max: 500, internal: true }),
    ],
  },
];

/** key → step */
export const STEP_BY_KEY = Object.fromEntries(STEPS.map((s) => [s.key, s]));

/** サーバーが受け付ける分類名（これ以外を送らない） */
export const SECTION_KEYS = STEPS.map((s) => s.key);
