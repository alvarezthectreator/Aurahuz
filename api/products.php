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

    $storefront = trim((string) ($_GET['storefront'] ?? ''));
    $sql = 'SELECT id, name, short_description, description, benefits, ingredients, price, old_price, image, tag
            FROM products
            WHERE is_active = 1';
    $parameters = [];
    if ($storefront !== '') {
        $sql .= ' AND storefront = ?';
        $parameters[] = $storefront;
    }
    $sql .= ' ORDER BY created_at DESC';
    $statement = database()->prepare($sql);
    $statement->execute($parameters);
    $products = array_map(static function (array $product): array {
        $product['price'] = (float) $product['price'];
        $product['old_price'] = $product['old_price'] === null ? null : (float) $product['old_price'];
        $product['benefits'] = json_decode((string) $product['benefits'], true) ?: [];
        return $product;
    }, $statement->fetchAll());

    echo json_encode(['data' => $products], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    echo json_encode(['error' => 'Unable to load products.']);
}
