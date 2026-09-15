<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (!empty($_SESSION['admin_id'])) adminRedirect('index.php');

$error = $_SESSION['flash']['message'] ?? '';
if (!empty($_SESSION['flash'])) {
    unset($_SESSION['flash']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $statement = database()->prepare('SELECT id, name, password_hash FROM admins WHERE email = ? LIMIT 1');
    $statement->execute([strtolower(trim((string) ($_POST['email'] ?? '')))]);
    $admin = $statement->fetch();
    if (!$admin || !password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
        $error = 'Incorrect email or password.';
    } else {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        adminRedirect('index.php');
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin login — Aurahuz</title><link rel="stylesheet" href="admin.css"><link rel="stylesheet" href="aurahuz-theme.css"></head><body class="auth-page"><main class="auth-card"><p class="eyebrow">Aurahuz admin</p><h1>Welcome back</h1><?php if ($error): ?><p class="flash error"><?= adminEscape($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><label>Email<input type="email" name="email" autocomplete="email" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button type="submit">Sign in</button></form></main></body></html>
