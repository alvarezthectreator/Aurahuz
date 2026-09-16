<?php
declare(strict_types=1);

$config = [
    'host' => getenv('AURAHUZ_SMTP_HOST') ?: '',
    'port' => (int) (getenv('AURAHUZ_SMTP_PORT') ?: 465),
    'username' => getenv('AURAHUZ_SMTP_USERNAME') ?: '',
    'password' => getenv('AURAHUZ_SMTP_PASSWORD') ?: '',
    'encryption' => getenv('AURAHUZ_SMTP_ENCRYPTION') ?: 'ssl',
    'from_email' => getenv('AURAHUZ_MAIL_FROM') ?: '',
    'from_name' => getenv('AURAHUZ_MAIL_FROM_NAME') ?: 'Aurahuz',
    'admin_email' => getenv('AURAHUZ_ADMIN_EMAIL') ?: '',
    'support_email' => getenv('AURAHUZ_SUPPORT_EMAIL') ?: '',
    'base_url' => rtrim((string) (getenv('AURAHUZ_BASE_URL') ?: ''), '/')
];

$localConfig = __DIR__ . '/mail.local.php';
if (is_file($localConfig)) {
    $override = require $localConfig;
    if (is_array($override)) $config = array_replace($config, $override);
}

return $config;