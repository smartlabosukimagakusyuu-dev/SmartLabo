// Smart Labo AI — ホームページAIチャットデモ
//
// 重要: これは問い合わせチャットではなく「Smart Labo Worksを無料体験できるAIデモ」。
// CEO承認事項として、実際のSmart AI Router(smartlabo-worksサーバー)は現時点で
// 本番デプロイされておらず公開APIを持たないため、今回はUI/UXのみを実装し、
// AI生成部分はあらかじめ用意した高品質なサンプル回答(デモ回答)で代替している。
// 将来、smartlabo-worksの本番デプロイと公開APIが用意された段階で、
// respond()内のDEMO_HANDLERS呼び出しを実際のfetch('/api/ai/...')呼び出しへ
// 差し替えることを想定した構造にしてある(呼び出し元のUIコードは変更不要)。
//
// 将来拡張(音声会話・画面共有・資料アップロード・OCR・Whisper・予約機能・
// Google Calendar連携)は、DEMO_HANDLERSへハンドラーを追加する形で対応できる。

(function () {
  "use strict";

  var STORAGE_KEY = "slw_ai_chat_history_v1";

  var SUGGESTED_QUESTIONS = [
    "Smart Labo Worksとは？",
    "料金を教えて",
    "不動産会社では何ができますか？",
    "司法書士でも使えますか？",
    "Company Brainとは？",
    "AI Routerとは？",
    "デモを見たい",
    "営業メールを作って",
  ];

  var AGENT_LIST = [
    { name: "営業AI", tag: "体験できます" },
    { name: "契約AI", tag: "体験できます" },
    { name: "Company Brain", tag: "体験できます" },
    { name: "AI Router", tag: "体験できます" },
    { name: "不動産AI", tag: "体験できます" },
    { name: "司法書士AI", tag: "Coming Soon", soon: true },
    { name: "管理会社AI", tag: "Coming Soon", soon: true },
  ];

  var FALLBACK_MESSAGE =
    "申し訳ございません、その内容には今のデモではうまくお答えできませんでした。\n" +
    "もっと詳しく体験したい方は、無料デモのご予約、またはお問い合わせをご利用ください。";

  // ============================================================
  // デモ回答(実際に生成するとCEO指示があった6機能 + 情報系Q&A)
  // ============================================================

  var DEMO_HANDLERS = {
    salesEmail: function () {
      return (
        "【営業メール（物件紹介メール）のデモ生成】\n\n" +
        "件名：サンシティ南1条のご紹介\n\n" +
        "山田様\n\n" +
        "こんにちは、Smart Labo不動産の佐藤です。いつもお世話になっております。\n\n" +
        "本日は駅徒歩5分、南向きで陽当たり良好な「サンシティ南1条」をご紹介いたします。ご興味がございましたら、ぜひ一度内覧にお越しください。\n\n" +
        "佐藤 一郎\n\n" +
        "※ これは営業AIによるデモ生成です。実際の製品では、顧客名・物件名・おすすめポイントなどを入力するだけで、このような文章を自動生成します。"
      );
    },
    listing: function () {
      return (
        "【物件紹介文のデモ生成】\n\n" +
        "札幌市中央区に位置するこちらの中古マンションは、駅から徒歩5分という利便性の高い立地にあります。南向きの室内は陽当たりが良好で、リフォーム済みのキッチンにより、日々の暮らしを快適にお過ごしいただけます。\n\n" +
        "※ これは営業AIによるデモ生成です。誇大な表現は使わず、入力された情報のみをもとに作成する設計です。"
      );
    },
    appraisal: function () {
      return (
        "【査定コメントのデモ生成】\n\n" +
        "本物件の査定価格は2,980万円と算出いたしました。この価格は、周辺の成約事例・築年数・立地条件を踏まえて算出しております。断定的な保証は行っておらず、あくまで参考情報としてご案内するものです。\n\n" +
        "※ これは契約AIによるデモ生成です。「必ず売れる」といった断定表現は使用しない設計になっています。"
      );
    },
    faq: function () {
      return (
        "【Company Brainのデモ回答】\n\n" +
        "Q. 経費精算のやり方を教えて\n" +
        "A. 経理システム→経費申請からレシートを添付して申請してください。上長承認後、翌月25日に振込されます。\n" +
        "関連資料：経費精算マニュアル ／ 次のアクション：申請画面を開く\n\n" +
        "※ これはCompany Brainによるデモ回答です。実際の製品では、会社ごとに登録した独自のルール・マニュアルをもとに回答します。"
      );
    },
    companyIntro: function () {
      return (
        "【会社紹介のデモ生成】\n\n" +
        "Smart Labo Worksは、営業・契約・議事録・顧客管理までをひとつにまとめる、中小企業向けのAI経営プラットフォームです。Smart AI Routerが仕事内容から最適なAIを自動で選び、Company Brainが会社独自の知識を学習することで、会社専属のAIチームとして機能します。\n\n" +
        "※ これは会社紹介AIによるデモ生成です。"
      );
    },
    proposal: function () {
      return (
        "【提案書のデモ生成】\n\n" +
        "課題：問い合わせ対応や契約書作成に時間がかかり、経営判断に集中する時間が不足している。\n" +
        "解決策：Smart Labo Worksの導入により、営業メール・契約書作成・議事録整理をAIが支援。\n" +
        "期待効果：定型業務の時間を短縮し、顧客対応と経営判断に充てられる時間を創出。\n\n" +
        "※ これは契約AI（Claude担当）によるデモ生成です。「課題→解決策→期待効果」の構成で作成する設計です。"
      );
    },
  };

  var INFO_ANSWERS = {
    about:
      "Smart Labo Worksは「会社を動かすAI」をコンセプトにした、中小企業向けのAI経営プラットフォームです。営業・契約・議事録・顧客管理・Company Brainまで、ひとつのプラットフォームにまとまっています。",
    pricing:
      "β版・正式版・Enterpriseの3プランをご用意しています。詳細価格は個別にご案内していますので、下の「無料デモをご予約」からお問い合わせください。",
    realestate:
      "不動産会社様では、物件紹介文・査定コメント・物件紹介メール・内覧/来店予約案内をAIが自動作成できます。「物件紹介文を作って」のように話しかけてお試しください。",
    legal:
      "司法書士向けの専用テンプレートは現在準備中（Coming Soon）です。Smart Labo WorksはTemplate Engineという「テンプレートを追加するだけで新業種に対応できる」構造を採用しているため、順次対応予定です。",
    companyBrain:
      "Company Brainは、会社独自のルール・商品・マニュアル・FAQを学習するAIです。社員はマニュアルを探す必要がなく、AIに質問するだけで必要な情報にたどり着けます。「FAQを見せて」と話しかけるとデモ回答をご覧いただけます。",
    router:
      "Smart AI Routerは、質問や依頼の内容を判断して、OpenAI・Claude・Geminiの中から最適なAIを自動で選ぶSmart Labo独自の技術です。利用者がAIを選ぶ必要はありません。",
    demo:
      "実際の画面は無料デモからご覧いただけます。右上（またはメニュー内）の「デモを見る」ボタンからお試しください。",
  };

  var KEYWORD_RULES = [
    { test: /営業メール|メール.*作って|メールを作成/, handler: DEMO_HANDLERS.salesEmail },
    { test: /物件紹介文|紹介文.*作って/, handler: DEMO_HANDLERS.listing },
    { test: /査定/, handler: DEMO_HANDLERS.appraisal },
    { test: /FAQ|よくある質問|経費精算/, handler: DEMO_HANDLERS.faq },
    { test: /提案書/, handler: DEMO_HANDLERS.proposal },
    { test: /会社紹介|会社について|会社概要|Smart Labo Works.*とは|スマートラボ.*とは/, handler: DEMO_HANDLERS.companyIntro },
    { test: /料金|価格|いくら|プラン/, answer: INFO_ANSWERS.pricing },
    { test: /司法書士/, answer: INFO_ANSWERS.legal },
    { test: /不動産/, answer: INFO_ANSWERS.realestate },
    { test: /Company\s*Brain|カンパニーブレイン/i, answer: INFO_ANSWERS.companyBrain },
    { test: /AI\s*Router|ルーター|Router/i, answer: INFO_ANSWERS.router },
    { test: /デモ.*見たい|デモ.*見せて|画面.*見たい/, answer: INFO_ANSWERS.demo },
    { test: /Smart Labo Works.*とは|とは何|なにができる|何ができる/, answer: INFO_ANSWERS.about },
  ];

  function classify(text) {
    for (var i = 0; i < KEYWORD_RULES.length; i++) {
      if (KEYWORD_RULES[i].test.test(text)) return KEYWORD_RULES[i];
    }
    return null;
  }

  function respond(text) {
    var rule = classify(text);
    if (!rule) return FALLBACK_MESSAGE;
    return rule.handler ? rule.handler() : rule.answer;
  }

  // ============================================================
  // 会話履歴の保存(localStorage)
  // ============================================================

  function loadHistory() {
    try {
      var raw = window.localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function saveHistory(messages) {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(messages));
    } catch (e) {
      /* localStorageが使えない環境では履歴保存をあきらめる(致命的ではない) */
    }
  }

  // ============================================================
  // UI構築
  // ============================================================

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function buildAgentListHtml() {
    return (
      '<div class="ai-agent-list">' +
      AGENT_LIST.map(function (a) {
        return (
          '<div class="ai-agent-row"><span class="ai-agent-row__name">' +
          escapeHtml(a.name) +
          '</span><span class="ai-agent-row__tag' +
          (a.soon ? " ai-agent-row__tag--soon" : "") +
          '">' +
          escapeHtml(a.tag) +
          "</span></div>"
        );
      }).join("") +
      "</div>"
    );
  }

  function buildChipsHtml() {
    return (
      '<div class="ai-msg__chips">' +
      SUGGESTED_QUESTIONS.map(function (q) {
        return '<button type="button" class="ai-chip" data-question="' + escapeHtml(q) + '">' + escapeHtml(q) + "</button>";
      }).join("") +
      "</div>"
    );
  }

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.getElementById("aiChat");
    if (!root) return;

    var toggle = document.getElementById("aiChatToggle");
    var closeBtn = document.getElementById("aiChatClose");
    var messagesEl = document.getElementById("aiChatMessages");
    var form = document.getElementById("aiChatForm");
    var input = document.getElementById("aiChatInput");

    var history = loadHistory();

    function renderMessage(msg) {
      var el = document.createElement("div");
      el.className = "ai-msg ai-msg--" + msg.role;
      var bubble = document.createElement("div");
      bubble.className = "ai-msg__bubble";
      bubble.innerHTML = escapeHtml(msg.text).replace(/\n/g, "<br>") + (msg.extraHtml || "");
      el.appendChild(bubble);
      messagesEl.appendChild(el);
    }

    function scrollToBottom() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function pushMessage(role, text, extraHtml) {
      var msg = { role: role, text: text, extraHtml: extraHtml || "" };
      renderMessage(msg);
      scrollToBottom();
      history.push(msg);
      saveHistory(history);
    }

    function showTyping(callback) {
      var el = document.createElement("div");
      el.className = "ai-msg ai-msg--bot ai-msg--typing";
      el.innerHTML =
        '<div class="ai-msg__bubble"><span class="ai-typing-dot"></span><span class="ai-typing-dot"></span><span class="ai-typing-dot"></span></div>';
      messagesEl.appendChild(el);
      scrollToBottom();
      window.setTimeout(function () {
        el.remove();
        callback();
      }, 650);
    }

    function handleUserText(text) {
      if (!text.trim()) return;
      pushMessage("user", text);
      input.value = "";
      showTyping(function () {
        pushMessage("bot", respond(text));
      });
    }

    if (!history) {
      history = [];
      var greetingText =
        "こんにちは。私はSmart Labo AIです。\nSmart Labo Worksを体験できます。お気軽にご質問ください。";
      pushMessage("bot", greetingText, buildAgentListHtml());
      pushMessage("bot", "おすすめ質問はこちらです。", buildChipsHtml());
    } else {
      history.forEach(renderMessage);
      scrollToBottom();
    }

    toggle.addEventListener("click", function () {
      root.classList.toggle("is-open");
      if (root.classList.contains("is-open")) {
        window.setTimeout(function () {
          input.focus();
          scrollToBottom();
        }, 260);
      }
    });
    closeBtn.addEventListener("click", function () {
      root.classList.remove("is-open");
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      handleUserText(input.value);
    });

    messagesEl.addEventListener("click", function (e) {
      var chip = e.target.closest(".ai-chip");
      if (chip) handleUserText(chip.getAttribute("data-question"));
    });
  });
})();
