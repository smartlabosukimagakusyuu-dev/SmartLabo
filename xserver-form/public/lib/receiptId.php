<?php
// Smart Labo Works — 受付番号発行(SLW-YYYYMMDD-XXXX形式)

function slw_generate_receipt_id(?DateTimeImmutable $date = null): string
{
    $date = $date ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
    return 'SLW-' . $date->format('Ymd') . '-' . $random;
}
