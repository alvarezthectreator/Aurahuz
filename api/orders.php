<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
header('Content-Type: application/json; charset=utf-8');

function jsonInput(): array
{
    $payload = json_decode((string) file_get_contents('php://input'), true);
    return is_array($payload) ? $payload : [];
}

function orderCode(): string
{
    return 'AURAHUZ-' . strtoupper(bin2hex(random_bytes(4)));
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        throw new RuntimeException('Method not allowed.');
    }

    $payload = jsonInput();
    $customer = $payload['customer'] ?? [];
    $items = $payload['items'] ?? [];
    $name = trim((string) ($customer['name'] ?? ''));
    $email = strtolower(trim((string) ($customer['email'] ?? '')));
    $phone = trim((string) ($customer['phone'] ?? ''));
    $location = trim((string) ($customer['location'] ?? ''));
    $storefront = trim((string) ($payload['storefront'] ?? 'aurahuz'));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || $location === '' || !in_array($storefront, ['aurahuz', 'fitness'], true) || !is_array($items) || $items === []) {
        http_response_code(422);
        throw new InvalidArgumentException('Customer details and at least one product are required.');
    }

    $database = database();
    $database->beginTransaction();
    $productQuery = $database->prepare('SELECT id, name, price FROM products WHERE id = ? AND storefront = ? AND is_active = 1 LIMIT 1');
    $orderItems = [];
    $total = 0.0;

    foreach ($items as $item) {
        $productId = trim((string) ($item['product_id'] ?? ''));
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        if ($productId === '' || $quantity === false) {
            throw new InvalidArgumentException('Invalid order item.');
        }
        $productQuery->execute([$productId, $storefront]);
        $product = $productQuery->fetch();
        if (!$product) {
            throw new InvalidArgumentException('One of the selected products is unavailable.');
        }
        $unitPrice = (float) $product['price'];
        $total += $unitPrice * $quantity;
        $orderItems[] = [$product['id'], $product['name'], $quantity, $unitPrice];
    }

    $orderCode = orderCode();
    $orderStatement = $database->prepare(
        "INSERT INTO orders (order_code, storefront, customer_name, customer_email, customer_phone, delivery_location, total, payment_method, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'bank_transfer', 'awaiting_payment')"
    );
        $orderStatement->execute([$orderCode, $storefront, $name, $email, $phone, $location, $total]);
    $orderId = (int) $database->lastInsertId();

    $itemStatement = $database->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price) VALUES (?, ?, ?, ?, ?)');
    foreach ($orderItems as [$productId, $productName, $quantity, $unitPrice]) {
        $itemStatement->execute([$orderId, $productId, $productName, $quantity, $unitPrice]);
    }

    $database->commit();
    echo json_encode(['data' => ['id' => $orderId, 'order_code' => $orderCode, 'total' => $total, 'payment_status' => 'awaiting_payment']], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    if (isset($database) && $database instanceof PDO && $database->inTransaction()) {
        $database->rollBack();
    }
    if (http_response_code() < 400) {
        http_response_code($exception instanceof InvalidArgumentException ? 422 : 500);
    }
    echo json_encode(['error' => $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unable to create order.']);
}
