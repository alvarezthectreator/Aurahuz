<?php
declare(strict_types=1);

// Enable detailed errors for local development to diagnose HTTP 500s
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);
$adminErrorLog = dirname(__DIR__) . '/data/error.log';
if (!is_dir(dirname($adminErrorLog))) {
    @mkdir(dirname($adminErrorLog), 0755, true);
}
@ini_set('error_log', $adminErrorLog);

session_start();
require_once dirname(__DIR__) . '/api/database.php';

function adminEscape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminRedirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(403);
        exit('Invalid form token. Refresh the page and try again.');
    }
}

function requireAdmin(): void
{
    if (empty($_SESSION['admin_id'])) {
        $_SESSION['flash'] = ['message' => 'Please sign in to access the admin dashboard.', 'type' => 'error'];
        adminRedirect('login.php');
    }
}

function setFlash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function takeFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
