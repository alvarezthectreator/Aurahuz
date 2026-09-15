<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireAdmin();
require_once dirname(__DIR__) . '/api/smtp_mailer.php';

$config = require dirname(__DIR__) . '/config/mail.php';
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $mailer = new SmtpMailer($config);
    $sent = $mailer->send(
        (string) $config['admin_email'],
        'Aurahuz email delivery test',
        '<h2>Aurahuz email test</h2><p>This confirms whether your store can send mail through its configured SMTP account.</p>',
        'Aurahuz email test: this confirms whether your store can send mail through its configured SMTP account.'
    );
    $result = ['sent' => $sent, 'code' => $mailer->errorCode(), 'detail' => $mailer->error()];
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Test email — Aurahuz</title><link rel="stylesheet" href="admin.css"><link rel="stylesheet" href="aurahuz-theme.css"></head><body class="auth-page"><main class="auth-card"><p class="eyebrow">Aurahuz admin</p><h1>Email delivery test</h1><p class="muted">This sends one test message to the configured store inbox.</p><?php if ($result): ?><p class="flash <?= $result['sent'] ? '' : 'error' ?>"><strong><?= adminEscape($result['code']) ?></strong><br><?= adminEscape($result['sent'] ? 'Message accepted by the SMTP server.' : $result['detail']) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><button type="submit">Send test email</button></form><p class="muted"><a href="index.php?view=settings">Back to settings</a></p></main></body></html>
