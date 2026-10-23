<?php
/* ZENTIC - live ratings
   GET  -> average, count and latest reviews (public)
   POST -> a visitor submits a rating (name, rating 1-5, optional comment) */
define('ZENTIC', 1);
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function summary($items) {
    $n = count($items);
    $sum = 0;
    $dist = [0, 0, 0, 0, 0];
    foreach ($items as $it) {
        $r = (int) ($it['rating'] ?? 0);
        $sum += $r;
        if ($r >= 1 && $r <= 5) $dist[$r - 1]++;
    }
    $list = [];
    foreach (array_slice($items, 0, 30) as $it) {
        $list[] = [
            'name'    => (string) ($it['name'] ?? ''),
            'rating'  => (int) ($it['rating'] ?? 0),
            'comment' => (string) ($it['comment'] ?? ''),
            'time'    => (int) ($it['time'] ?? 0),
        ];
    }
    return ['count' => $n, 'average' => $n ? round($sum / $n, 1) : 0, 'dist' => $dist, 'items' => $list];
}

$store = z_read('ratings', []);
$items = (isset($store['items']) && is_array($store['items'])) ? $store['items'] : [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    out(summary($items));
}

/* ---------- submit ---------- */
$name    = trim((string) ($_POST['name'] ?? ''));
$comment = trim((string) ($_POST['comment'] ?? ''));
$rating  = (int) ($_POST['rating'] ?? 0);
$trap    = (string) ($_POST['website'] ?? '');   // hidden field: bots fill it in

if ($trap !== '') out(['ok' => true] + summary($items));   // pretend success to bots
if ($rating < 1 || $rating > 5) out(['ok' => false, 'error' => 'Please choose 1 to 5 stars.'], 422);

$clean = function ($s, $max) {
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    $s = preg_replace('/\s+/u', ' ', (string) $s);
    return mb_substr(trim($s), 0, $max);
};
$name    = $clean($name, 40);
$comment = $clean($comment, 300);
if ($name === '') $name = 'Anonymous';

$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'x') . '|zentic-salt');
$fp = fopen(Z_DATA . '/ratings.json', 'c+');
if (!$fp || !flock($fp, LOCK_EX)) out(['ok' => false, 'error' => 'Could not save right now. Please try again.'], 500);

$cur = json_decode((string) stream_get_contents($fp), true);
if (!is_array($cur)) $cur = [];
$cur += ['items' => [], 'ips' => []];
$now = time();

/* one rating per visitor per hour */
foreach ($cur['ips'] as $h => $t) { if ($t < $now - 3600) unset($cur['ips'][$h]); }
if (isset($cur['ips'][$ipHash])) {
    flock($fp, LOCK_UN); fclose($fp);
    out(['ok' => false, 'error' => 'You already rated us recently. Thank you!'], 429);
}

array_unshift($cur['items'], [
    'id'      => bin2hex(random_bytes(6)),
    'name'    => $name,
    'rating'  => $rating,
    'comment' => $comment,
    'time'    => $now,
]);
$cur['items'] = array_slice($cur['items'], 0, 1000);
$cur['ips'][$ipHash] = $now;

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($cur, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

out(['ok' => true] + summary($cur['items']));
