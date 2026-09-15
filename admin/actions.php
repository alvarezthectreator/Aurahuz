<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') adminRedirect('index.php');
verifyCsrf();
$returnView = (string) ($_POST['return_view'] ?? 'overview');
if (!in_array($returnView, ['overview', 'products', 'payments'], true)) $returnView = 'overview';

function productId(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function uploadedProductImagePath(): ?string
{
    if (!isset($_FILES['image_upload']) || $_FILES['image_upload']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['image_upload'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('The uploaded image could not be processed.');
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes, true)) {
        throw new InvalidArgumentException('Please upload a valid image file: JPG, PNG, GIF, WEBP or SVG.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension === '' && $mime === 'image/svg+xml') {
        $extension = 'svg';
    }
    if ($extension === '' && $mime === 'image/png') {
        $extension = 'png';
    }
    if ($extension === '' && $mime === 'image/jpeg') {
        $extension = 'jpg';
    }
    if ($extension === '' && $mime === 'image/gif') {
        $extension = 'gif';
    }
    if ($extension === '' && $mime === 'image/webp') {
        $extension = 'webp';
    }

    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'product-image';
    $targetDir = dirname(__DIR__) . '/img/uploads';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new InvalidArgumentException('The product image folder could not be created.');
    }

    $targetName = $safeName . '-' . uniqid('', true) . '.' . $extension;
    $targetPath = $targetDir . '/' . $targetName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new InvalidArgumentException('The uploaded image could not be saved.');
    }

    return 'img/uploads/' . $targetName;
}

function productData(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $id = productId((string) ($_POST['product_id'] ?? $name));
    $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
    $oldPrice = trim((string) ($_POST['old_price'] ?? ''));
    $storefront = (string) ($_POST['storefront'] ?? 'aurahuz');
    $uploadedImage = uploadedProductImagePath();
    $image = trim((string) ($_POST['image'] ?? ''));
    if ($uploadedImage !== null) {
        $image = $uploadedImage;
    } elseif ($image === '') {
        $image = 'img/imgi_6_mkyxtj9t4k.jpg';
    }
    $benefits = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($_POST['benefits'] ?? '')) ?: [])));
    if ($id === '' || $name === '' || $price === false || $price < 0) {
        throw new InvalidArgumentException('Product ID, name, price and image path are required.');
    }
    if ($oldPrice !== '' && (!is_numeric($oldPrice) || (float) $oldPrice < 0)) throw new InvalidArgumentException('Old price must be a valid amount.');
    if (!in_array($storefront, ['aurahuz', 'fitness'], true)) throw new InvalidArgumentException('Choose a valid storefront.');
    return [
        $id, $storefront, $name, trim((string) ($_POST['short_description'] ?? '')), trim((string) ($_POST['description'] ?? '')),
        json_encode($benefits, JSON_UNESCAPED_UNICODE), trim((string) ($_POST['ingredients'] ?? '')), (float) $price,
        $oldPrice === '' ? null : (float) $oldPrice, $image, trim((string) ($_POST['tag'] ?? '')) ?: null,
        isset($_POST['is_active']) ? 1 : 0
    ];
}

try {
    $database = database();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create_product') {
        $data = productData();
        $database->prepare('INSERT INTO products (id, storefront, name, short_description, description, benefits, ingredients, price, old_price, image, tag, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute($data);
        setFlash('Product created.');
    } elseif ($action === 'update_product') {
        $originalId = productId((string) ($_POST['original_id'] ?? ''));
        $data = productData();
        if ($originalId === '') throw new InvalidArgumentException('Product not found.');
        $database->prepare('UPDATE products SET storefront = ?, name = ?, short_description = ?, description = ?, benefits = ?, ingredients = ?, price = ?, old_price = ?, image = ?, tag = ?, is_active = ? WHERE id = ?')->execute(array_merge(array_slice($data, 1), [$originalId]));
        setFlash('Product updated.');
    } elseif ($action === 'delete_product') {
        $database->prepare('DELETE FROM products WHERE id = ?')->execute([productId((string) ($_POST['product_id'] ?? ''))]);
        setFlash('Product deleted.');
    } elseif ($action === 'create_bank_account') {
        $database->prepare('INSERT INTO bank_accounts (bank_name, account_name, account_number, instructions, is_active) VALUES (?, ?, ?, ?, ?)')->execute([
            trim((string) ($_POST['bank_name'] ?? '')), trim((string) ($_POST['account_name'] ?? '')), trim((string) ($_POST['account_number'] ?? '')), trim((string) ($_POST['instructions'] ?? '')), isset($_POST['is_active']) ? 1 : 0
        ]);
        setFlash('Bank account created.');
    } elseif ($action === 'update_bank_account') {
        $database->prepare('UPDATE bank_accounts SET bank_name = ?, account_name = ?, account_number = ?, instructions = ?, is_active = ? WHERE id = ?')->execute([
            trim((string) ($_POST['bank_name'] ?? '')), trim((string) ($_POST['account_name'] ?? '')), trim((string) ($_POST['account_number'] ?? '')), trim((string) ($_POST['instructions'] ?? '')), isset($_POST['is_active']) ? 1 : 0, (int) ($_POST['bank_id'] ?? 0)
        ]);
        setFlash('Bank account updated.');
    } elseif ($action === 'delete_bank_account') {
        $database->prepare('DELETE FROM bank_accounts WHERE id = ?')->execute([(int) ($_POST['bank_id'] ?? 0)]);
        setFlash('Bank account deleted.');
    } else {
        throw new InvalidArgumentException('Unknown action.');
    }
} catch (Throwable $exception) {
    setFlash($exception instanceof PDOException && $exception->getCode() === '23000' ? 'That product ID already exists.' : $exception->getMessage(), 'error');
}
adminRedirect('index.php?view=' . rawurlencode($returnView));
