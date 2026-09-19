<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
header('Content-Type: application/json; charset=utf-8');

function paymentResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') paymentResponse(['error' => 'Only POST requests are allowed.'], 405);

$orderCode = trim((string) ($_POST['order_id'] ?? ''));
$receipt = $_FILES['receipt'] ?? null;
if ($orderCode === '' || !is_array($receipt) || ($receipt['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    paymentResponse(['error' => 'Choose an order and upload your payment receipt.'], 422);
}
if (($receipt['size'] ?? 0) < 1 || $receipt['size'] > 5 * 1024 * 1024 || !is_uploaded_file((string) $receipt['tmp_name'])) {
    paymentResponse(['error' => 'Receipt must be a valid file no larger than 5 MB.'], 422);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $receipt['tmp_name']);
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
if (!isset($extensions[$mime])) paymentResponse(['error' => 'Upload a JPG, PNG, or PDF receipt.'], 422);

$directory = dirname(__DIR__) . '/data/receipts';
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) paymentResponse(['error' => 'Receipt storage is unavailable.'], 500);
$filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
$receiptPath = $directory . '/' . $filename;
if (!move_uploaded_file((string) $receipt['tmp_name'], $receiptPath)) paymentResponse(['error' => 'Receipt could not be saved.'], 500);

try {
    $db = database();
    $db->beginTransaction();
    $statement = $db->prepare('SELECT * FROM orders WHERE order_code = ? FOR UPDATE');
    $statement->execute([$orderCode]);
    $order = $statement->fetch();
    if (!$order) throw new InvalidArgumentException('Order not found.');
    if (!in_array($order['payment_status'], ['awaiting_payment', 'payment_pending_confirmation'], true)) throw new InvalidArgumentException('This order cannot accept another receipt.');

    $approvalToken = bin2hex(random_bytes(32));
    $relativePath = 'data/receipts/' . $filename;
    $db->prepare("UPDATE orders SET payment_method = 'bank_transfer', payment_status = 'payment_pending_confirmation', receipt_path = ?, approval_token_hash = ?, payment_submitted_at = NOW() WHERE id = ?")
        ->execute([$relativePath, hash('sha256', $approvalToken), $order['id']]);
    $db->commit();
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    @unlink($receiptPath);
    paymentResponse(['error' => $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Payment confirmation could not be saved.'], $exception instanceof InvalidArgumentException ? 422 : 500);
}

$mailStatus = ['customer' => 'MAIL_NOT_ATTEMPTED', 'admin' => 'MAIL_NOT_ATTEMPTED'];
try {
    require_once __DIR__ . '/smtp_mailer.php';
    $mailConfig = require dirname(__DIR__) . '/config/mail.php';
    $mailer = new SmtpMailer($mailConfig);
    $name = htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($orderCode, ENT_QUOTES, 'UTF-8');
    $total = 'NGN ' . number_format((float) $order['total'], 2);
    $approvalUrl = rtrim((string) ($mailConfig['base_url'] ?? ''), '/') . '/api/approve_payment.php?order=' . rawurlencode($orderCode) . '&token=' . rawurlencode($approvalToken);
    $safeApprovalUrl = htmlspecialchars($approvalUrl, ENT_QUOTES, 'UTF-8');
    $customerHtml = aurahuzEmailTemplate('PAYMENT RECEIPT RECEIVED', 'Thanks, ' . $name, '<p style="margin:0 0 16px">We received your payment receipt for order <strong style="color:#26382d">' . $safeCode . '</strong>. We are reviewing it now and will email you as soon as your order is confirmed.</p><div style="padding:14px 12px;background:#f2f6f0;border-radius:10px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#78857c">Order total <strong style="float:right;color:#26382d;font-size:14px;letter-spacing:0;text-transform:none">' . $total . '</strong></div>');
    $mailStatus['customer'] = $mailer->send((string) $order['customer_email'], 'Order update ' . $orderCode, $customerHtml, 'Your payment receipt for order ' . $orderCode . ' has been received.') ? 'MAIL_SENT' : $mailer->errorCode();
    $adminHtml = aurahuzEmailTemplate('PAYMENT RECEIPT RECEIVED', 'Payment confirmation pending', '<p style="margin:0 0 16px">A payment receipt was submitted for order <strong style="color:#26382d">' . $safeCode . '</strong>.</p><p style="margin:0 0 16px">Customer: <strong style="color:#26382d">' . $name . '</strong></p><div style="padding:14px 12px;background:#f2f6f0;border-radius:10px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#78857c">Order total <strong style="float:right;color:#26382d;font-size:14px;letter-spacing:0;text-transform:none">' . $total . '</strong></div><p style="margin:20px 0 0"><a href="' . $safeApprovalUrl . '" style="display:inline-block;padding:13px 18px;border-radius:8px;background:#0d4b34;color:#fff;font-size:12px;font-weight:700;text-decoration:none">Approve order</a></p><p style="margin:14px 0 0;font-size:11px;color:#849087">Use this button after checking the payment receipt. Approval will mark the order as paid and send the customer a confirmation email.</p>');
    $adminStatus = $mailer->send((string) $mailConfig['admin_email'], 'Order update ' . $orderCode, $adminHtml, 'A payment receipt was submitted for order ' . $orderCode . '. Please review the order in the Aurahuz admin area.');
    $mailStatus['admin'] = $adminStatus ? 'MAIL_SENT' : $mailer->errorCode();
} catch (Throwable $exception) {
    error_log('Aurahuz payment mail failed: ' . $exception->getMessage());
    $mailStatus = ['customer' => 'MAILER_UNAVAILABLE', 'admin' => 'MAILER_UNAVAILABLE'];
}

paymentResponse(['success' => true, 'order_id' => $orderCode, 'status' => 'payment_pending_confirmation', 'mail_status' => $mailStatus]);
