<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$orderId = filter_input(INPUT_GET, 'order', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$orderId) {
    http_response_code(404);
    exit('Receipt not found.');
}

$statement = database()->prepare('SELECT receipt_path FROM orders WHERE id = ?');
$statement->execute([$orderId]);
$receiptPath = (string) $statement->fetchColumn();
$filename = basename($receiptPath);
if (!str_starts_with($receiptPath, 'data/receipts/') || !preg_match('/^[a-f0-9]{40}\.(jpg|png|pdf)$/', $filename)) {
    http_response_code(404);
    exit('Receipt not found.');
}

$filePath = dirname(__DIR__) . '/data/receipts/' . $filename;
if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('Receipt file is unavailable.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    http_response_code(404);
    exit('Receipt file is unavailable.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($filePath));
header('Content-Disposition: inline; filename="payment-receipt-' . $orderId . '.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
