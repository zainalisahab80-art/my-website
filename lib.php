<?php
/* ZENTIC - shared helpers (not a public page) */
if (!defined('ZENTIC')) { http_response_code(403); exit; }

define('Z_DATA', __DIR__ . '/data');

function z_read($name, $default = []) {
    $f = Z_DATA . '/' . $name . '.json';
    if (!is_file($f)) return $default;
    $d = json_decode((string) @file_get_contents($f), true);
    return is_array($d) ? $d : $default;
}

/* Safe write: lock + write to a temp file + rename */
function z_write($name, $data) {
    if (!is_dir(Z_DATA)) @mkdir(Z_DATA, 0755, true);
    $f   = Z_DATA . '/' . $name . '.json';
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, $f)) {
        $ok = @file_put_contents($f, $json, LOCK_EX) !== false;
        @unlink($tmp);
        return $ok;
    }
    return true;
}

function z_defaults() {
    return [
        'email'       => 'zainalisahab80@gmail.com',
        'phone_intl'  => '+92 305 3698083',
        'phone_local' => '0305 3698083',
        'whatsapp'    => '923053698083',
        'wa_message'  => 'Hi Zentic, I want to discuss a design project.',
        'socials'     => ['facebook' => '', 'instagram' => '', 'tiktok' => '', 'youtube' => '', 'linkedin' => ''],
    ];
}

function z_settings() {
    $d = z_defaults();
    $s = z_read('settings', []);
    foreach (['email', 'phone_intl', 'phone_local', 'whatsapp', 'wa_message'] as $k) {
        if (isset($s[$k]) && is_string($s[$k]) && $s[$k] !== '') $d[$k] = $s[$k];
    }
    if (isset($s['socials']) && is_array($s['socials'])) {
        foreach ($d['socials'] as $k => $_) {
            if (isset($s['socials'][$k]) && is_string($s['socials'][$k])) $d['socials'][$k] = $s['socials'][$k];
        }
    }
    return $d;
}
