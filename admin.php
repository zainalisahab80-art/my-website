<?php
/* =========================================================
   ZENTIC - Admin dashboard (only the owner can log in)
   Open:  yourwebsite.com/admin.php
   ========================================================= */
define('ZENTIC', 1);
require __DIR__ . '/lib.php';
$cfg = require __DIR__ . '/config.php';

ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_name('zentic_admin');
session_start();

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

$lockFile = Z_DATA . '/login-attempts.json';

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
function csrf_ok() {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}
function flash($type, $msg) { $_SESSION['flash'] = [$type, $msg]; }
function go($tab) { header('Location: admin.php?tab=' . $tab); exit; }

/* password hash: data/admin.json (set from the dashboard) wins over config.php */
$adminFile = z_read('admin', []);
$hash = (!empty($adminFile['hash']) && is_string($adminFile['hash'])) ? $adminFile['hash'] : $cfg['admin_hash'];
$defaultPass = empty($adminFile['hash']);

/* ---------- login attempt limiter (5 wrong tries -> 15 min wait) ---------- */
function attempts_load($file) {
    $d = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    return is_array($d) ? $d : [];
}
function attempts_save($file, $d) {
    $now = time();
    foreach ($d as $k => $a) { if (($a['until'] ?? 0) < $now && ($a['t'] ?? 0) < $now - 900) unset($d[$k]); }
    @file_put_contents($file, json_encode($d), LOCK_EX);
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'x';

$error = '';
$action = (string) ($_POST['action'] ?? '');

if ($action === 'logout' && csrf_ok()) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($action === 'login') {
    $att = attempts_load($lockFile);
    $rec = $att[$ip] ?? ['n' => 0, 'until' => 0, 't' => 0];
    if (!csrf_ok()) {
        $error = 'Session expired. Please try again.';
    } elseif (($rec['until'] ?? 0) > time()) {
        $mins = (int) ceil(($rec['until'] - time()) / 60);
        $error = "Too many wrong attempts. Try again in $mins minute(s).";
    } else {
        $u = (string) ($_POST['username'] ?? '');
        $p = (string) ($_POST['password'] ?? '');
        $userOk = hash_equals($cfg['admin_user'], $u);
        $passOk = password_verify($p, $hash);
        if ($userOk && $passOk) {
            unset($att[$ip]);
            attempts_save($lockFile, $att);
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
            header('Location: admin.php');
            exit;
        }
        $rec['n'] = ($rec['n'] ?? 0) + 1;
        $rec['t'] = time();
        if ($rec['n'] >= 5) { $rec['until'] = time() + 900; $rec['n'] = 0; }
        $att[$ip] = $rec;
        attempts_save($lockFile, $att);
        $error = 'Wrong username or password.';
    }
}

$loggedIn = !empty($_SESSION['admin']);

/* ======================= logged-in actions ======================= */
if ($loggedIn && $action !== '' && $action !== 'login' && $action !== 'logout') {
    $tab = 'overview';
    if (!csrf_ok()) {
        flash('err', 'Session expired. Please refresh and try again.');
        go('overview');
    }

    /* ---------- upload ---------- */
    if ($action === 'upload') {
        $type = (($_POST['type'] ?? 'design') === 'logo') ? 'logo' : 'design';
        $tab  = $type === 'logo' ? 'clients' : 'gallery';
        $title    = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80);
        $category = mb_substr(trim((string) ($_POST['category'] ?? '')), 0, 40);
        $client   = mb_substr(trim((string) ($_POST['client'] ?? '')), 0, 60);
        if ($category === '') $category = 'Designs';

        $files = $_FILES['pictures'] ?? null;
        if (!$files || empty($files['name'][0])) {
            flash('err', 'Please choose at least one picture.');
            go($tab);
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $dir  = $type === 'logo' ? 'images/logos' : 'images/gallery';
        $full = __DIR__ . '/' . $dir;
        if (!is_dir($full)) @mkdir($full, 0755, true);

        $new = [];
        $bad = [];
        $count = min(count($files['name']), 20);
        for ($i = 0; $i < $count; $i++) {
            $orig = (string) $files['name'][$i];
            $err  = $files['error'][$i];
            if ($err !== UPLOAD_ERR_OK) { $bad[] = $orig . ' (upload failed, code ' . $err . ')'; continue; }
            if ($files['size'][$i] > $cfg['max_bytes']) { $bad[] = $orig . ' (larger than 8 MB)'; continue; }
            if (!is_uploaded_file($files['tmp_name'][$i])) { $bad[] = $orig . ' (invalid upload)'; continue; }
            $fi   = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($files['tmp_name'][$i]);
            if (!isset($allowed[$mime]) || @getimagesize($files['tmp_name'][$i]) === false) {
                $bad[] = $orig . ' (not a valid JPG / PNG / WEBP / GIF)'; continue;
            }
            $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($files['tmp_name'][$i], $full . '/' . $name)) {
                $bad[] = $orig . ' (could not save - check folder permissions)'; continue;
            }
            @chmod($full . '/' . $name, 0644);

            $t = $title;
            if ($t === '' || $count > 1) {
                $base = trim(preg_replace('/[_\-]+/', ' ', pathinfo($orig, PATHINFO_FILENAME)));
                $t = ($title !== '' ? $title . ' ' . ($i + 1) : ($base !== '' ? mb_substr($base, 0, 80) : 'Untitled'));
            }
            $item = ['title' => $t, 'img' => $dir . '/' . $name];
            if ($type === 'design') $item['category'] = $category;
            if ($client !== '') $item['client'] = $client;
            $new[] = $item;
        }
        if ($new) {
            $cur = z_read('gallery', []) + ['gallery' => [], 'logos' => []];
            $key = $type === 'logo' ? 'logos' : 'gallery';
            $cur[$key] = array_merge(array_reverse($new), $cur[$key]);
            if (!z_write('gallery', $cur)) {
                flash('err', 'Pictures uploaded but data/gallery.json could not be saved (check folder permissions).');
                go($tab);
            }
        }
        $msg = count($new) . ' picture(s) added.';
        if ($bad) { flash('err', $msg . ' Failed: ' . implode('; ', $bad)); }
        else { flash('ok', $msg); }
        go($tab);
    }

    /* ---------- edit picture details ---------- */
    if ($action === 'edit') {
        $img = (string) ($_POST['img'] ?? '');
        $tab = (strpos($img, 'images/logos/') === 0) ? 'clients' : 'gallery';
        $cur = z_read('gallery', []) + ['gallery' => [], 'logos' => []];
        $found = false;
        foreach (['gallery', 'logos'] as $k) {
            foreach ($cur[$k] as $i => $it) {
                if (($it['img'] ?? '') === $img) {
                    $t = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80);
                    if ($t !== '') $cur[$k][$i]['title'] = $t;
                    $c = mb_substr(trim((string) ($_POST['client'] ?? '')), 0, 60);
                    if ($c === '') unset($cur[$k][$i]['client']); else $cur[$k][$i]['client'] = $c;
                    if ($k === 'gallery') {
                        $cat = mb_substr(trim((string) ($_POST['category'] ?? '')), 0, 40);
                        $cur[$k][$i]['category'] = $cat !== '' ? $cat : 'Designs';
                    }
                    $found = true;
                }
            }
        }
        if ($found && z_write('gallery', $cur)) flash('ok', 'Saved.');
        else flash('err', 'Could not save that change.');
        go($tab);
    }

    /* ---------- delete picture ---------- */
    if ($action === 'delete') {
        $img = (string) ($_POST['img'] ?? '');
        $tab = (strpos($img, 'images/logos/') === 0) ? 'clients' : 'gallery';
        if (!preg_match('#^images/(gallery|logos)/[A-Za-z0-9._-]+$#', $img) || strpos($img, '..') !== false) {
            flash('err', 'Invalid picture.');
            go($tab);
        }
        $cur = z_read('gallery', []) + ['gallery' => [], 'logos' => []];
        $found = false;
        $title = '';
        foreach (['gallery', 'logos'] as $k) {
            $keep = [];
            foreach ($cur[$k] as $it) {
                if (($it['img'] ?? '') === $img) { $found = true; $title = (string) ($it['title'] ?? ''); continue; }
                $keep[] = $it;
            }
            $cur[$k] = $keep;
        }
        if ($found && z_write('gallery', $cur)) {
            $path = __DIR__ . '/' . $img;
            if (is_file($path)) @unlink($path);
            flash('ok', 'Deleted: ' . $title);
        } else {
            flash('err', 'That picture was not found (already deleted?).');
        }
        go($tab);
    }

    /* ---------- delete a rating ---------- */
    if ($action === 'delete_rating') {
        $id = (string) ($_POST['id'] ?? '');
        $cur = z_read('ratings', []) + ['items' => [], 'ips' => []];
        $keep = [];
        foreach ($cur['items'] as $it) { if (($it['id'] ?? '') !== $id) $keep[] = $it; }
        $cur['items'] = $keep;
        if (z_write('ratings', $cur)) flash('ok', 'Rating deleted.');
        else flash('err', 'Could not delete that rating.');
        go('ratings');
    }

    /* ---------- website settings ---------- */
    if ($action === 'save_settings') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $wa    = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('err', 'Please enter a valid email address.'); go('settings'); }
        if (strlen($wa) < 8 || strlen($wa) > 15) { flash('err', 'WhatsApp number must be 8-15 digits with country code, e.g. 923053698083.'); go('settings'); }
        $s = [
            'email'       => $email,
            'whatsapp'    => $wa,
            'phone_intl'  => mb_substr(trim(strip_tags((string) ($_POST['phone_intl'] ?? ''))), 0, 30),
            'phone_local' => mb_substr(trim(strip_tags((string) ($_POST['phone_local'] ?? ''))), 0, 30),
            'wa_message'  => mb_substr(trim(strip_tags((string) ($_POST['wa_message'] ?? ''))), 0, 200),
            'socials'     => [],
        ];
        foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'] as $k) {
            $u = trim((string) ($_POST['social_' . $k] ?? ''));
            if ($u !== '' && (!preg_match('#^https?://#i', $u) || !filter_var($u, FILTER_VALIDATE_URL) || strlen($u) > 200)) {
                flash('err', ucfirst($k) . ' link must start with https:// (or leave it empty).');
                go('settings');
            }
            $s['socials'][$k] = $u;
        }
        if (z_write('settings', $s)) flash('ok', 'Settings saved. The website is updated.');
        else flash('err', 'Could not save settings (check that the data folder is writable).');
        go('settings');
    }

    /* ---------- change password ---------- */
    if ($action === 'change_password') {
        $cur = (string) ($_POST['current'] ?? '');
        $n1  = (string) ($_POST['new1'] ?? '');
        $n2  = (string) ($_POST['new2'] ?? '');
        if (!password_verify($cur, $hash)) { flash('err', 'Current password is wrong.'); go('account'); }
        if (strlen($n1) < 8) { flash('err', 'New password must be at least 8 characters.'); go('account'); }
        if ($n1 !== $n2) { flash('err', 'The two new passwords do not match.'); go('account'); }
        if (z_write('admin', ['hash' => password_hash($n1, PASSWORD_DEFAULT)])) flash('ok', 'Password changed. Use the new one next time you log in.');
        else flash('err', 'Could not save the new password (check that the data folder is writable).');
        go('account');
    }

    flash('err', 'Unknown action.');
    go('overview');
}

/* ======================= page data ======================= */
$tab = (string) ($_GET['tab'] ?? 'overview');
$tabs = ['overview' => 'Overview', 'gallery' => 'Designs', 'clients' => 'Clients & Logos', 'ratings' => 'Ratings', 'settings' => 'Settings', 'account' => 'Password'];
if (!isset($tabs[$tab])) $tab = 'overview';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$items = ['gallery' => [], 'logos' => []];
$ratings = ['items' => []];
$S = z_settings();
$cats = [];
if ($loggedIn) {
    $d = z_read('gallery', []);
    $items['gallery'] = $d['gallery'] ?? [];
    $items['logos']   = $d['logos'] ?? [];
    $r = z_read('ratings', []);
    $ratings['items'] = (isset($r['items']) && is_array($r['items'])) ? $r['items'] : [];
    foreach ($items['gallery'] as $g) if (!empty($g['category'])) $cats[$g['category']] = true;
    foreach (['Food Posters', 'Drink Posters', 'Branding', 'Social Media', 'Posters', 'Packaging'] as $c) $cats[$c] = true;
    $cats = array_keys($cats);
}
$rCount = count($ratings['items']);
$rSum = 0;
foreach ($ratings['items'] as $it) $rSum += (int) ($it['rating'] ?? 0);
$rAvg = $rCount ? round($rSum / $rCount, 1) : 0;

function stars($n) { $n = (int) round($n); return str_repeat('★', $n) . str_repeat('☆', 5 - $n); }

function pic_list($list, $isLogo) {
    global $cats;
    if (!$list) { echo '<div class="hint" style="margin-bottom:18px">Nothing here yet.</div>'; return; }
    echo '<div class="mgrid">';
    foreach ($list as $it) {
        $img = (string) ($it['img'] ?? '');
        echo '<div class="mitem">';
        echo '<img src="' . h($img) . '" alt="" loading="lazy">';
        echo '<form method="post" class="mform">';
        echo '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
        echo '<input type="hidden" name="action" value="edit"><input type="hidden" name="img" value="' . h($img) . '">';
        echo '<input type="text" name="title" value="' . h($it['title'] ?? '') . '" maxlength="80" placeholder="Title" aria-label="Title">';
        echo '<input type="text" name="client" value="' . h($it['client'] ?? '') . '" maxlength="60" placeholder="Client name" aria-label="Client name">';
        if (!$isLogo) echo '<input type="text" name="category" value="' . h($it['category'] ?? '') . '" maxlength="40" placeholder="Category" aria-label="Category">';
        echo '<button type="submit" class="savebtn">Save</button>';
        echo '</form>';
        echo '<form method="post" data-msg="Delete &quot;' . h($it['title'] ?? '') . '&quot; permanently?" onsubmit="return confirm(this.dataset.msg);">';
        echo '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
        echo '<input type="hidden" name="action" value="delete"><input type="hidden" name="img" value="' . h($img) . '">';
        echo '<button type="submit" class="delbtn">Delete</button></form>';
        echo '</div>';
    }
    echo '</div>';
}

function upload_form($type) {
    global $cats;
    $isLogo = $type === 'logo';
    ?>
    <form method="post" enctype="multipart/form-data" class="upForm">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="type" value="<?= $isLogo ? 'logo' : 'design' ?>">
      <div class="field-row">
        <div class="field"><label>Client name (optional)</label><input type="text" name="client" maxlength="60" placeholder="e.g. Wings Cafe"></div>
        <div class="field"><label>Title (optional)</label><input type="text" name="title" maxlength="80" placeholder="<?= $isLogo ? 'e.g. Wings Logo' : 'e.g. Cheesy Pizza Poster' ?>"></div>
      </div>
      <?php if (!$isLogo): ?>
      <div class="field">
        <label>Category</label>
        <input type="text" name="category" maxlength="40" list="catList" placeholder="e.g. Food Posters" value="Designs">
        <datalist id="catList"><?php foreach ($cats as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist>
        <div class="hint">Pick one or type a new category - a filter button is created automatically.</div>
      </div>
      <?php endif; ?>
      <div class="field">
        <label>Pictures</label>
        <input type="file" name="pictures[]" class="picInput" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
        <div class="hint">JPG, PNG, WEBP or GIF, up to 8 MB each. You can choose many at once.</div>
        <div class="preview"></div>
      </div>
      <div class="actions"><button class="btn" type="submit">Upload</button></div>
    </form>
    <?php
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin | Zentic</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { min-height: 100vh; background: radial-gradient(ellipse at 80% 20%, #10346b 0%, transparent 40%), linear-gradient(120deg,#071225,#09172d 56%,#061020); }
    .admin-wrap { max-width: 980px; margin: 0 auto; padding: 36px 22px 80px; }
    .admin-wrap.narrow { max-width: 520px; padding-top: 60px; }
    .admin-top { display:flex; justify-content:space-between; align-items:center; gap:14px; margin-bottom: 22px; flex-wrap:wrap; }
    .admin-top .right { display:flex; gap:10px; align-items:center; }
    .tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:22px; }
    .tabs a { padding:10px 16px; border:1px solid var(--line); border-radius:100px; color:var(--muted); text-decoration:none; font-size:13.5px; font-weight:600; transition:.2s; }
    .tabs a:hover { color:#fff; border-color:#438fff; }
    .tabs a.on { background:var(--blue); border-color:var(--blue); color:#fff; }
    .admin-card { padding: clamp(22px,4vw,34px); border:1px solid var(--line); border-radius: 10px; background: linear-gradient(160deg,#0f2342,#081326 85%); margin-bottom:22px; }
    .admin-card h1, .admin-card h2.t { margin:0 0 6px; font-family:"Space Grotesk",Arial,sans-serif; font-size:26px; letter-spacing:-.04em; }
    .admin-card > p { margin:0 0 22px; color: var(--muted); font-size:14px; }
    .alert { margin-bottom:18px; padding:13px 16px; border-radius:8px; font-size:14px; }
    .alert.err { background:#ff5d6c1f; border:1px solid #ff5d6c70; color:#ffb0b8; }
    .alert.ok  { background:#1faa5922; border:1px solid #1faa5970; color:#9ff0c2; }
    .alert.warn { background:#ffc65a1f; border:1px solid #ffc65a70; color:#ffe2a6; }
    .alert a { color:#fff; }
    .hint { margin-top:6px; color:var(--muted-2); font-size:12px; }
    .actions { display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-top:8px; }
    .linkbtn { background:none; border:1px solid var(--line); color:var(--muted); border-radius:8px; padding:10px 16px; font:inherit; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
    .linkbtn:hover { color:#fff; border-color:#438fff; }
    .mh { margin:22px 0 12px; font-family:"Space Grotesk",Arial,sans-serif; font-size:17px; letter-spacing:-.02em; color:#dce9ff; }
    .mgrid { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:14px; }
    .mitem { padding:10px; border:1px solid var(--line); border-radius:8px; background:#050d1c; }
    .mitem img { width:100%; aspect-ratio:1; object-fit:cover; border-radius:6px; background:#0d1f3b; }
    .mform { display:grid; gap:6px; margin-top:10px; }
    .mform input { width:100%; padding:8px 10px; border:1px solid #ffffff2a; border-radius:6px; background:#071225; color:#fff; font:inherit; font-size:12.5px; }
    .mform input:focus { outline:none; border-color:var(--blue); }
    .savebtn { padding:8px; border:1px solid #438fff70; border-radius:6px; background:#1677ff22; color:#bcd6ff; font:inherit; font-size:12.5px; font-weight:700; cursor:pointer; }
    .savebtn:hover { background:var(--blue); color:#fff; }
    .delbtn { margin-top:8px; width:100%; padding:8px; border:1px solid #ff5d6c70; border-radius:6px; background:#ff5d6c1a; color:#ffb0b8; font:inherit; font-size:12.5px; font-weight:700; cursor:pointer; transition:.2s; }
    .delbtn:hover { background:#ff5d6c; color:#fff; }
    .preview { display:grid; grid-template-columns:repeat(auto-fill,minmax(90px,1fr)); gap:10px; margin-top:12px; }
    .preview img { width:100%; aspect-ratio:1; object-fit:cover; border-radius:8px; border:1px solid var(--line); }
    .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin-bottom:22px; }
    .kpi { padding:20px; border:1px solid var(--line); border-radius:10px; background:#050d1c; }
    .kpi b { display:block; font-family:"Space Grotesk",Arial,sans-serif; font-size:34px; letter-spacing:-.05em; color:var(--blue-light); }
    .kpi span { color:var(--muted-2); font-size:12px; letter-spacing:1.4px; text-transform:uppercase; }
    .rrow { display:flex; gap:14px; justify-content:space-between; align-items:flex-start; padding:16px 0; border-top:1px solid var(--line); }
    .rrow:first-of-type { border-top:0; }
    .rrow .st { color:#ffc65a; letter-spacing:2px; font-size:15px; }
    .rrow .who { font-weight:700; }
    .rrow .when { color:var(--muted-2); font-size:12px; margin-left:8px; font-weight:400; }
    .rrow p { margin:6px 0 0; color:var(--muted); font-size:14px; line-height:1.6; word-break:break-word; }
    .rrow .delbtn { margin:0; width:auto; padding:8px 14px; }
    .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:0 18px; }
    @media (max-width:700px) { .grid2 { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php if (!$loggedIn): ?>
<div class="admin-wrap narrow">
  <div class="admin-top">
    <a class="logo" href="index.html"><i></i>ZENTIC<sup>®</sup></a>
    <a class="linkbtn" href="index.html">← Back to website</a>
  </div>
  <div class="admin-card">
    <h1>Admin login</h1>
    <p>Only the owner can sign in here.</p>
    <?php if ($error): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="login">
      <div class="field"><label for="username">Username</label><input type="text" id="username" name="username" required autofocus autocapitalize="off" autocomplete="username"></div>
      <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required autocomplete="current-password"></div>
      <div class="actions"><button class="btn" type="submit">Log in</button></div>
    </form>
  </div>
</div>
<?php else: ?>
<div class="admin-wrap">
  <div class="admin-top">
    <a class="logo" href="index.html"><i></i>ZENTIC<sup>®</sup> <span style="font-size:12px;color:var(--muted-2);letter-spacing:2px;margin-left:8px">DASHBOARD</span></a>
    <div class="right">
      <a class="linkbtn" href="index.html" target="_blank" rel="noopener">View website ↗</a>
      <form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="logout"><button class="linkbtn" type="submit">Log out</button></form>
    </div>
  </div>

  <nav class="tabs" aria-label="Dashboard">
    <?php foreach ($tabs as $k => $label): ?>
      <a href="admin.php?tab=<?= h($k) ?>" class="<?= $k === $tab ? 'on' : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($flash): ?><div class="alert <?= $flash[0] === 'ok' ? 'ok' : 'err' ?>"><?= h($flash[1]) ?></div><?php endif; ?>

<?php if ($tab === 'overview'): ?>
  <?php if ($defaultPass): ?>
    <div class="alert warn"><b>Security:</b> you are still using the default password. <a href="admin.php?tab=account">Change it now →</a></div>
  <?php endif; ?>
  <div class="kpis">
    <div class="kpi"><b><?= count($items['gallery']) ?></b><span>Designs / posters</span></div>
    <div class="kpi"><b><?= count($items['logos']) ?></b><span>Client logos</span></div>
    <div class="kpi"><b><?= $rCount ? h(number_format($rAvg, 1)) : '–' ?></b><span>Average rating</span></div>
    <div class="kpi"><b><?= $rCount ?></b><span>Total ratings</span></div>
  </div>
  <div class="admin-card">
    <h1>Welcome 👋</h1>
    <p>Everything on your website can be managed from here. No code needed.</p>
    <div class="actions">
      <a class="btn" href="admin.php?tab=gallery">Add / delete designs</a>
      <a class="btn secondary" href="admin.php?tab=clients">Clients &amp; logos</a>
      <a class="btn secondary" href="admin.php?tab=ratings">See ratings</a>
      <a class="btn secondary" href="admin.php?tab=settings">Website settings</a>
    </div>
  </div>

<?php elseif ($tab === 'gallery'): ?>
  <div class="admin-card">
    <h1>Add designs / posters</h1>
    <p>Upload posters and designs for your clients. They appear on the Gallery page and Home page right away.</p>
    <?php upload_form('design'); ?>
  </div>
  <div class="admin-card">
    <h2 class="t">Your designs (<?= count($items['gallery']) ?>)</h2>
    <p>Change a title, client or category and press Save, or delete a picture (this also deletes the file).</p>
    <?php pic_list($items['gallery'], false); ?>
  </div>

<?php elseif ($tab === 'clients'): ?>
  <div class="admin-card">
    <h1>Add client logos</h1>
    <p>Logos of the clients you work for. They show in the “Our clients” section on Home and in the Logo collection on the Gallery page.</p>
    <?php upload_form('logo'); ?>
  </div>
  <div class="admin-card">
    <h2 class="t">Your logos (<?= count($items['logos']) ?>)</h2>
    <p>Put the client's name in “Client name” so it shows under the logo.</p>
    <?php pic_list($items['logos'], true); ?>
  </div>

<?php elseif ($tab === 'ratings'): ?>
  <div class="admin-card">
    <h1>Ratings <?php if ($rCount): ?><span style="color:#ffc65a;font-size:20px">★ <?= h(number_format($rAvg, 1)) ?></span><?php endif; ?></h1>
    <p><?= $rCount ?> rating(s) from visitors. New ratings appear on your website instantly. Delete any spam or unfair entry below.</p>
    <?php if (!$ratings['items']): ?>
      <div class="hint">No ratings yet.</div>
    <?php else: foreach ($ratings['items'] as $it): ?>
      <div class="rrow">
        <div>
          <div><span class="st"><?= h(stars($it['rating'] ?? 0)) ?></span></div>
          <div class="who"><?= h($it['name'] ?? '') ?><span class="when"><?= !empty($it['time']) ? h(date('d M Y, H:i', (int) $it['time'])) : '' ?></span></div>
          <?php if (!empty($it['comment'])): ?><p><?= h($it['comment']) ?></p><?php endif; ?>
        </div>
        <form method="post" data-msg="Delete this rating?" onsubmit="return confirm(this.dataset.msg);">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <input type="hidden" name="action" value="delete_rating">
          <input type="hidden" name="id" value="<?= h($it['id'] ?? '') ?>">
          <button type="submit" class="delbtn">Delete</button>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>

<?php elseif ($tab === 'settings'): ?>
  <div class="admin-card">
    <h1>Website settings</h1>
    <p>Change your contact details and social links. The whole website updates automatically.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="save_settings">
      <div class="grid2">
        <div class="field"><label>Email</label><input type="email" name="email" required maxlength="120" value="<?= h($S['email']) ?>"></div>
        <div class="field"><label>WhatsApp number</label><input type="text" name="whatsapp" required maxlength="20" value="<?= h($S['whatsapp']) ?>"><div class="hint">Digits only with country code, no + or 0. Example: 923053698083</div></div>
        <div class="field"><label>Phone (shown on website)</label><input type="text" name="phone_intl" maxlength="30" value="<?= h($S['phone_intl']) ?>"></div>
        <div class="field"><label>Phone (local format)</label><input type="text" name="phone_local" maxlength="30" value="<?= h($S['phone_local']) ?>"></div>
      </div>
      <div class="field"><label>First WhatsApp message</label><input type="text" name="wa_message" maxlength="200" value="<?= h($S['wa_message']) ?>"><div class="hint">This text is pre-filled when a visitor taps a WhatsApp button.</div></div>

      <h2 class="mh">Social media links</h2>
      <p class="hint" style="margin:0 0 14px">Paste the full link (starting with https://). Empty ones are hidden from the footer.</p>
      <div class="grid2">
        <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn'] as $k => $label): ?>
          <div class="field"><label><?= h($label) ?></label><input type="url" name="social_<?= h($k) ?>" maxlength="200" placeholder="https://" value="<?= h($S['socials'][$k]) ?>"></div>
        <?php endforeach; ?>
      </div>
      <div class="actions"><button class="btn" type="submit">Save settings</button></div>
    </form>
  </div>

<?php elseif ($tab === 'account'): ?>
  <div class="admin-card" style="max-width:520px">
    <h1>Change password</h1>
    <p>Use a strong password that only you know.</p>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="change_password">
      <div class="field"><label>Current password</label><input type="password" name="current" required autocomplete="current-password"></div>
      <div class="field"><label>New password</label><input type="password" name="new1" required minlength="8" autocomplete="new-password"></div>
      <div class="field"><label>New password again</label><input type="password" name="new2" required minlength="8" autocomplete="new-password"></div>
      <div class="actions"><button class="btn" type="submit">Change password</button></div>
    </form>
  </div>
<?php endif; ?>
</div>

<script>
  document.querySelectorAll('.upForm').forEach(function (form) {
    var input = form.querySelector('.picInput');
    var prev = form.querySelector('.preview');
    input.addEventListener('change', function () {
      prev.innerHTML = '';
      Array.prototype.slice.call(input.files, 0, 20).forEach(function (f) {
        if (!/^image\//.test(f.type)) return;
        var img = document.createElement('img');
        img.alt = f.name;
        img.src = URL.createObjectURL(f);
        img.onload = function () { URL.revokeObjectURL(img.src); };
        prev.appendChild(img);
      });
    });
  });
</script>
<?php endif; ?>
</body>
</html>
