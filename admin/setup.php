<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

try {
    // Allow creating a new admin at any time — do not redirect when admins exist.
    // Keep a lightweight DB check so the page fails early if the database is not available.
    database()->query('SELECT 1');
} catch (Throwable $exception) {
    exit('Database connection failed. Check the Aurahuz database configuration.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        $error = 'Enter your name, a valid email, and a password of at least 10 characters.';
    } else {
        $statement = database()->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)');
        $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        $_SESSION['admin_id'] = (int) database()->lastInsertId();
        $_SESSION['admin_name'] = $name;
        adminRedirect('index.php');
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Create admin — Aurahuz</title><link rel="stylesheet" href="admin.css"><link rel="stylesheet" href="aurahuz-theme.css"></head><body class="auth-page"><main class="auth-card"><p class="eyebrow">Aurahuz admin</p><h1>Create your admin account</h1><p class="muted">This secure one-time setup is available only until the first admin is created.</p><?php if ($error): ?><p class="flash error"><?= adminEscape($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" minlength="10" autocomplete="new-password" required></label><button type="submit">Create admin account</button></form></main></body></html>
