<?php
// Public: contact details + social links (read-only) used by every page.
define('ZENTIC', 1);
require __DIR__ . '/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(z_settings(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
