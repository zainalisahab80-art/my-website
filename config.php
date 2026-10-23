<?php
/* =========================================================
   ZENTIC - admin settings
   Username: zentic
   Password: zenticadmin   (stored below only as a secure hash)
   ========================================================= */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'config.php') { http_response_code(403); exit; }

return [
    'admin_user' => 'zentic',
    'admin_hash' => '$2y$10$Pw3yqrKofIoZwoVFJrbg..z8wwFzbVCR/9svoSDE8wvJMnuCGlaC.',
    'max_bytes'  => 8 * 1024 * 1024,   // 8 MB per picture
];
