<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

function approvalPage(string $title, string $message, int $status = 200): never
{
    http_response_code($status);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#fff8e8;color:#3a2108;font:16px Arial,sans-serif}.card{max-width:480px;margin:24px;padding:34px;border-radius:16px;background:#fff;text-align:center;box-shadow:0 18px 50px #5d390333}h1{margin:0 0 12px;font-size:24px}p{margin:0;color:#705d49;line-height:1.5}</style><main class="card"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></main></html>';
    exit;
}

$orderCode = trim((string) ($_GET['order'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
if ($orderCode === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) approvalPage('Invalid approval link', 'This payment approval link is incomplete or invalid.', 400);

try {
    $db = database();
    $db->beginTransaction();
    $statement = $db->prepare('SELECT * FROM orders WHERE order_code = ? FOR UPDATE');
    $statement->execute([$orderCode]);
    $order = $statement->fetch();
    if (!$order) approvalPage('Order not found', 'This order could not be found.', 404);
    if ($order['payment_status'] === 'payment_approved') {
        $db->commit();
        approvalPage('Payment already approved', 'This order has already been approved.');
    }
    if ($order['payment_status'] !== 'payment_pending_confirmation' || !hash_equals((string) $order['approval_token_hash'], hash('sha256', $token))) {
        $db->rollBack();
        approvalPage('Invalid approval link', 'This link cannot be used to approve the payment.', 403);
    }
    $db->prepare("UPDATE orders SET payment_status = 'payment_approved', approval_token_hash = NULL, payment_approved_at = NOW() WHERE id = ?")->execute([$order['id']]);
    $db->commit();
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('Aurahuz payment approval failed: ' . $exception->getMessage());
    approvalPage('Approval failed', 'Please try the approval link again later.', 500);
}

try {
    require_once __DIR__ . '/smtp_mailer.php';
    $mailConfig = require dirname(__DIR__) . '/config/mail.php';
    $mailer = new SmtpMailer($mailConfig);
    $name = htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8');
    $total = 'NGN ' . number_format((float) $order['total'], 2);
    $html = aurahuzEmailTemplate('PAYMENT CONFIRMED', 'Thanks, ' . $name, '<p style="margin:0 0 16px">Your payment for order <strong style="color:#26382d">' . htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8') . '</strong> has been approved.</p><div style="padding:14px 12px;background:#f2f6f0;border-radius:10px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#78857c">Amount paid <strong style="float:right;color:#26382d;font-size:14px;letter-spacing:0;text-transform:none">' . $total . '</strong></div><p style="margin:16px 0 0">We will contact you about delivery.</p>');
    $mailer->send((string) $order['customer_email'], 'Aurahuz order confirmed: ' . $orderCode, $html, 'Your Aurahuz order ' . $orderCode . ' has been confirmed.');
} catch (Throwable $exception) {
    error_log('Aurahuz confirmation mail failed: ' . $exception->getMessage());
}

approvalPage('Payment approved', 'The payment was approved and the customer confirmation email was sent.');
