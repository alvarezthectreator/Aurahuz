<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        throw new RuntimeException('Method not allowed.');
    }

    $statement = database()->query('SELECT bank_name, account_name, account_number, instructions FROM bank_accounts WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    $account = $statement->fetch();
    if (!$account) {
        http_response_code(404);
        throw new RuntimeException('No active bank account is configured.');
    }
    echo json_encode(['data' => $account], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['error' => 'Unable to load bank-transfer details.']);
}
