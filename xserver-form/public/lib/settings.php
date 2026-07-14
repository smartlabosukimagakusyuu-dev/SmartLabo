<?php
// Smart Labo Works — 秘密情報を含まない設定値(Git管理対象)
// ドメイン名は公開情報であり秘密情報ではないため、config.php(秘密情報専用)とは
// 分離してコードの一部としてバージョン管理する。

const SLW_ALLOWED_ORIGINS = [
    'https://smartlaboworks.com',
    'https://www.smartlaboworks.com',
    // 移行期間中の暫定URL。正式ドメインへの切替が完了したら削除すること。
    'https://smartlabosukimagakusyuu-dev.github.io',
];
