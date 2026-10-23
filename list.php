<?php
// Public: returns pictures added through the admin panel (read-only).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$file = __DIR__ . '/data/gallery.json';
$out = ['gallery' => [], 'logos' => []];

if (is_file($file)) {
    $d = json_decode((string) file_get_contents($file), true);
    if (is_array($d)) {
        foreach (['gallery', 'logos'] as $k) {
            if (!empty($d[$k]) && is_array($d[$k])) $out[$k] = array_values($d[$k]);
        }
    }
}
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
