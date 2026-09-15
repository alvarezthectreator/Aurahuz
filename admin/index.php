<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
requireAdmin();

$db = database();
$views = ['overview', 'products', 'orders', 'payments', 'settings'];
$view = (string) ($_GET['view'] ?? 'overview');
if (!in_array($view, $views, true)) $view = 'overview';
$products = $db->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();
$accounts = $db->query('SELECT * FROM bank_accounts ORDER BY is_active DESC, id DESC')->fetchAll();
$orders = $db->query('SELECT id, order_code, storefront, customer_name, customer_email, customer_phone, delivery_location, total, payment_method, payment_status, receipt_path, created_at FROM orders ORDER BY created_at DESC LIMIT 20')->fetchAll();
$countProducts = count($products);
$countOrders = (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$countCustomers = (int) $db->query('SELECT COUNT(DISTINCT customer_email) FROM orders')->fetchColumn();
$revenue = (float) $db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'payment_approved'")->fetchColumn();
$pending = (int) $db->query("SELECT COUNT(*) FROM orders WHERE payment_status IN ('awaiting_payment', 'payment_pending_confirmation')")->fetchColumn();
$chartStart = (new DateTimeImmutable('first day of this month'))->modify('-5 months');
$salesStatement = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month_key, COALESCE(SUM(total), 0) AS total FROM orders WHERE payment_status = 'payment_approved' AND created_at >= ? GROUP BY month_key");
$salesStatement->execute([$chartStart->format('Y-m-d')]);
$salesByMonth = array_column($salesStatement->fetchAll(), 'total', 'month_key');
$chartSales = [];
for ($month = $chartStart; $month <= new DateTimeImmutable('first day of this month'); $month = $month->modify('+1 month')) {
    $chartSales[] = ['label' => $month->format('M'), 'total' => (float) ($salesByMonth[$month->format('Y-m')] ?? 0)];
}
$maxMonthlySales = max(1, ...array_column($chartSales, 'total'));
$editingProduct = $editingBank = null;
if (isset($_GET['edit_product'])) { $q = $db->prepare('SELECT * FROM products WHERE id = ?'); $q->execute([$_GET['edit_product']]); $editingProduct = $q->fetch() ?: null; $view = 'products'; }
if (isset($_GET['edit_bank'])) { $q = $db->prepare('SELECT * FROM bank_accounts WHERE id = ?'); $q->execute([(int) $_GET['edit_bank']]); $editingBank = $q->fetch() ?: null; $view = 'payments'; }
$benefits = $editingProduct ? json_decode((string) $editingProduct['benefits'], true) : [];
if (!is_array($benefits)) $benefits = [];
$flash = takeFlash();

function icon(string $name): string { $paths = ['overview'=>'<rect x="3" y="3" width="7" height="7" rx="1"/>
<rect x="14" y="3" width="7" height="7" rx="1"/>
<rect x="3" y="14" width="7" height="7" rx="1"/>
<rect x="14" y="14" width="7" height="7" rx="1"/>','products'=>'<path d="M4 7.5 8 4h8l4 3.5V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7.5Z"/>
<path d="M4 9h16M9 4l1 5m5-5-1 5"/>','orders'=>'<path d="M3 4h2l2 12h10l2-8H7"/>
<circle cx="9" cy="20" r="1"/>
<circle cx="17" cy="20" r="1"/>','payments'=>'<rect x="3" y="5" width="18" height="15" rx="2"/>
<path d="M3 10h18M7 15h3"/>','settings'=>'<circle cx="12" cy="12" r="3"/>
<path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.2 2.2-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.04 1.56V20.4h-3.12v-.1a1.7 1.7 0 0 0-1.04-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-2.2-2.2.06-.06A1.7 1.7 0 0 0 6.72 15a1.7 1.7 0 0 0-1.56-1.04h-.1v-3.12h.1A1.7 1.7 0 0 0 6.72 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.2-2.2.06.06A1.7 1.7 0 0 0 10.46 5.26 1.7 1.7 0 0 0 11.5 3.7v-.1h3.12v.1a1.7 1.7 0 0 0 1.04 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.2 2.2-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.56 1.04h.1v3.12h-.1A1.7 1.7 0 0 0 19.4 15Z"/>','search'=>'<circle cx="11" cy="11" r="6"/>
<path d="m20 20-4-4"/>','more'=>'<circle cx="5" cy="12" r="1" fill="currentColor"/>
<circle cx="12" cy="12" r="1" fill="currentColor"/>
<circle cx="19" cy="12" r="1" fill="currentColor"/>','arrow'=>'<path d="m9 18 6-6-6-6"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','logout'=>'<path d="M10 17l5-5-5-5M15 12H3"/>
<path d="M21 3v18"/>']; return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>'; }
function state(string $status): array { return match($status) { 'payment_approved'=>['approved','Paid'], 'payment_pending_confirmation'=>['pending','Receipt submitted'], default=>['awaiting','Awaiting payment'] }; }
function orderStatus(string $status): string { [$style,$label] = state($status); return '<span class="status '.$style.'">'.$label.'</span>'; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>
<?= ucfirst($view) ?> — Aurahuz admin</title>
<link rel="stylesheet" href="admin.css">
<link rel="stylesheet" href="admin-responsive.css">
<link rel="stylesheet" href="admin-dashboard.css">
<link rel="stylesheet" href="aurahuz-theme.css">
<style>.receipt-link{display:block;width:max-content;margin-top:7px;color:#247b50;font-size:11px;font-weight:700;text-decoration:none}.receipt-link:hover{text-decoration:underline}</style>
</head>
<body>
<div class="admin-shell">
<aside class="sidebar" id="admin-sidebar">
<a class="brand" href="index.php">
<span class="brand-mark">Z</span>
<span>Aurahuz</span>
</a>
<p class="nav-label">Store management</p>
<nav class="side-nav">
<?php foreach (['overview'=>'Overview','products'=>'Products','orders'=>'Orders','payments'=>'Payments'] as $slug=>$label): ?>
<a href="?view=<?= $slug ?>" class="<?= $view === $slug ? 'active' : '' ?>">
<?= icon($slug) ?>
<span>
<?= $label ?>
</span>
</a>
<?php endforeach; ?>
</nav>
<p class="nav-label bottom-label">General</p>
<nav class="side-nav">
<a href="?view=settings" class="<?= $view === 'settings' ? 'active' : '' ?>">
<?= icon('settings') ?>
<span>Settings</span>
</a>
<a class="sign-out" href="logout.php">
<?= icon('logout') ?>
<span>Sign out</span>
</a>
</nav>
<div class="sidebar-foot">
<i>
</i> Store online</div>
</aside>
<button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Close navigation">
</button>
<main class="workspace">
<header class="workspace-header">
<button class="menu-toggle" type="button" aria-label="Open navigation" aria-controls="admin-sidebar" aria-expanded="false" data-sidebar-toggle>
<svg viewBox="0 0 24 24" aria-hidden="true">
<path d="M4 7h16M4 12h16M4 17h16"/>
</svg>
</button>
<div>
<p class="kicker">Aurahuz stores</p>
<h1>
<?= $view === 'overview' ? 'Overview' : ucfirst($view) ?>
</h1>
</div>
<label class="global-search">
<?= icon('search') ?>
<input type="search" placeholder="Search products and orders" aria-label="Search">
<kbd>⌘ K</kbd>
</label>
<div class="profile">
<span class="avatar">
<?= adminEscape(strtoupper(substr((string) $_SESSION['admin_name'],0,1))) ?>
</span>
<span>
<strong>
<?= adminEscape((string) $_SESSION['admin_name']) ?>
</strong>
<small>Store manager</small>
</span>
</div>
</header>
<?php if ($flash): ?>
<p class="flash <?= $flash['type'] === 'error' ? 'error' : '' ?>">
<?= adminEscape($flash['message']) ?>
</p>
<?php endif; ?>

<?php if ($view === 'overview'): ?>
<section class="page-heading">
<div>
<p class="section-kicker">Your store at a glance</p>
<h2>Good to see you back.</h2>
<p>Keep an eye on orders, payments, and both product collections.</p>
</div>
<a class="primary-button" href="?view=products#product-form">
<?= icon('plus') ?> Add product</a>
</section>
<section class="stat-grid">
<article class="stat-card">
<div>
<span>Total revenue</span>
<?= icon('more') ?>
</div>
<strong>₦<?= number_format($revenue,2) ?>
</strong>
<small>
<b class="up">↗ Live</b> confirmed payments</small>
</article>
<article class="stat-card">
<div>
<span>Total customers</span>
<?= icon('more') ?>
</div>
<strong>
<?= number_format($countCustomers) ?>
</strong>
<small>
<b class="up">↗ Live</b> unique customer emails</small>
</article>
<article class="stat-card">
<div>
<span>Total orders</span>
<?= icon('more') ?>
</div>
<strong>
<?= number_format($countOrders) ?>
</strong>
<small>
<b class="<?= $pending ? 'warn' : 'up' ?>">
<?= $pending ? $pending.' pending' : '↗ All clear' ?>
</b> payment activity</small>
</article>
<article class="stat-card">
<div>
<span>Total products</span>
<?= icon('more') ?>
</div>
<strong>
<?= number_format($countProducts) ?>
</strong>
<small>
<b class="up">↗ <?= count(array_filter($products, fn($p)=>(int)$p['is_active']===1)) ?> active</b> in the catalog</small>
</article>
</section>
<section class="overview-grid">
<article class="card">
<div class="card-heading">
<div>
<h2>Sales activity</h2>
<p>Confirmed revenue by month</p>
</div>
<span class="select-pill">Last 6 months</span>
</div>
<div class="chart-total">₦<?= number_format($revenue,2) ?>
<span>Live sales</span>
</div>
<div class="chart" aria-label="Confirmed sales for the last six months">
<?php foreach ($chartSales as $sale): $height = $sale['total'] > 0 ? max(14, ($sale['total'] / $maxMonthlySales) * 100) : 2; ?>
<div class="bar-group" title="<?= $sale['label'] ?>: ₦<?= number_format($sale['total'], 2) ?>">
<div class="bar-plot">
<span class="bar-value">₦<?= $sale['total'] >= 1000 ? number_format($sale['total'] / 1000, 0) . 'k' : number_format($sale['total'], 0) ?></span>
<i class="bar mint<?= $sale['total'] <= 0 ? ' is-empty' : '' ?>" style="height:<?= $height ?>%"></i>
</div>
<small><?= $sale['label'] ?></small>
</div>
<?php endforeach; ?>
</div>
</article>
<article class="card">
<div class="card-heading">
<div>
<h2>Latest orders</h2>
<p>Most recent customer activity</p>
</div>
<a class="view-all" href="?view=orders">View all <?= icon('arrow') ?>
</a>
</div>
<?php if($orders): foreach(array_slice($orders,0,5) as $order): ?>
<div class="recent-order">
<span class="order-avatar">
<?= adminEscape(strtoupper(substr($order['customer_name'],0,1))) ?>
</span>
<span>
<strong>
<?= adminEscape($order['customer_name']) ?>
</strong>
<small>
<?= adminEscape($order['order_code']) ?> · <?= state($order['payment_status'])[1] ?>
</small>
</span>
<b>₦<?= number_format((float)$order['total'],2) ?>
</b>
</div>
<?php endforeach; else: ?>
<div class="empty-state">
<span>⌁</span>
<strong>No orders yet</strong>
<p>Customer orders will show up here as soon as checkout is used.</p>
</div>
<?php endif; ?>
</article>
</section>
<section class="card table-card">
<div class="card-heading">
<div>
<h2>Recent transactions</h2>
<p>
<?= $countOrders ?> total order<?= $countOrders===1?'':'s' ?> in your store</p>
</div>
<a class="view-all" href="?view=orders">Open orders <?= icon('arrow') ?>
</a>
</div>
<?php if($orders): ?>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Order</th>
<th>Customer</th>
<th>Date</th>
<th>Amount</th>
<th>Status</th>
</tr>
</thead>
<tbody>
<?php foreach(array_slice($orders,0,5) as $order): ?>
<tr>
<td>
<strong>
<?= adminEscape($order['order_code']) ?>
</strong>
</td>
<td>
<?= adminEscape($order['customer_name']) ?>
</td>
<td>
<?= date('M j, Y',strtotime($order['created_at'])) ?>
</td>
<td>₦<?= number_format((float)$order['total'],2) ?>
</td>
<td>
<?= orderStatus($order['payment_status']) ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state compact">
<strong>Your transaction history is empty.</strong>
<p>Orders will appear here after a customer completes checkout.</p>
</div>
<?php endif; ?>
</section>

<?php elseif($view === 'products'): ?>
<section class="page-heading">
<div>
<p class="section-kicker">Catalog</p>
<h2>
<?= $editingProduct?'Edit product':'Add new product' ?>
</h2>
<p>Build clear, inviting collections for the fashion and fitness storefronts.</p>
</div>
<a class="outline-button" href="?view=products">Cancel</a>
</section>
<?php require __DIR__ . '/product-table.php'; ?>
<div class="product-layout">
<section class="card form-card" id="product-editor">
<h2>General information</h2>
<form method="post" action="actions.php" id="product-form" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
<input type="hidden" name="return_view" value="products">
<input type="hidden" name="action" value="<?= $editingProduct?'update_product':'create_product' ?>">
<?php if($editingProduct): ?>
<input type="hidden" name="original_id" value="<?= adminEscape($editingProduct['id']) ?>">
<?php endif; ?>
<div class="form-grid">
<label>Product ID<input name="product_id" value="<?= adminEscape($editingProduct['id']??'') ?>" placeholder="e.g. calming-tea" <?= $editingProduct?'readonly':'' ?> required>
</label>
<label>Product name<input name="name" value="<?= adminEscape($editingProduct['name']??'') ?>" placeholder="e.g. Woman lace lingerie" required>
</label>
<label>Storefront<select name="storefront"><option value="aurahuz" <?= ($editingProduct['storefront']??'aurahuz') === 'aurahuz' ? 'selected' : '' ?>>Aurahuz Fashion</option><option value="fitness" <?= ($editingProduct['storefront']??'') === 'fitness' ? 'selected' : '' ?>>Aurahuz Fitness</option></select>
</label>
<label class="full">Short description<textarea name="short_description" required>
<?= adminEscape($editingProduct['short_description']??'') ?>
</textarea>
</label>
<label class="full">Full description<textarea name="description" required>
<?= adminEscape($editingProduct['description']??'') ?>
</textarea>
</label>
<label class="full">Benefits <em>One benefit per line</em>
<textarea name="benefits">
<?= adminEscape(implode("\n",$benefits)) ?>
</textarea>
</label>
<label class="full">Ingredients<textarea name="ingredients" required>
<?= adminEscape($editingProduct['ingredients']??'') ?>
</textarea>
</label>
<label class="full">Upload image<input type="file" name="image_upload" accept=".png,.jpg,.jpeg,.gif,.svg,.webp,image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
</label>
</div>
<h3>Pricing and display</h3>
<div class="form-grid">
<label>Price (₦)<input type="number" min="0" step="0.01" name="price" value="<?= adminEscape((string)($editingProduct['price']??'')) ?>" required>
</label>
<label>Old price (optional)<input type="number" min="0" step="0.01" name="old_price" value="<?= adminEscape((string)($editingProduct['old_price']??'')) ?>">
</label>
<label>Badge or tag<input name="tag" value="<?= adminEscape($editingProduct['tag']??'') ?>" placeholder="Bestseller">
</label>
<label class="toggle-label">
<input type="checkbox" name="is_active" value="1" <?= !$editingProduct||(int)$editingProduct['is_active']===1?'checked':'' ?>>
<i>
</i>Show in shop</label>
</div>
<button class="primary-button" type="submit">✓ <?= $editingProduct?'Save changes':'Add product' ?>
</button>
</form>
</section>
<aside class="product-side">
<section class="card image-card">
<h2>Product image</h2>
<div class="image-preview">
<?php if($editingProduct): ?>
<img src="../<?= adminEscape($editingProduct['image']) ?>" alt="Product preview">
<?php else: ?>
<span>Image preview</span>
<?php endif; ?>
</div>
<label>Image file path or URL<input name="image" form="product-form" value="<?= adminEscape($editingProduct['image']??'') ?>" placeholder="img/product.jpg">
</label>
<p>Upload a file or paste a local project path such as img/product.jpg.</p>
</section>
<section class="card collection-card">
<h2>Catalog snapshot</h2>
<strong>
<?= $countProducts ?> products</strong>
<p>
<?= count(array_filter($products,fn($p)=>(int)$p['is_active']===1)) ?> currently visible to customers.</p>
</section>
</aside>
</div>
<?php if (false): ?>
<section class="card table-card">
<div class="card-heading">
<div>
<h2>Products</h2>
<p>Manage the collection in your storefront.</p>
</div>
</div>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Product</th>
<th>Price</th>
<th>Badge</th>
<th>Visibility</th>
<th>
</th>
</tr>
</thead>
<tbody>
<?php foreach($products as $product): ?>
<tr>
<td class="product-cell">
<img src="../<?= adminEscape($product['image']) ?>" alt="">
<span>
<strong>
<?= adminEscape($product['name']) ?>
</strong>
<small>
<?= adminEscape($product['id']) ?>
</small>
</span>
</td>
<td>₦<?= number_format((float)$product['price'],2) ?>
</td>
<td>
<?= adminEscape($product['tag']?:'—') ?>
</td>
<td>
<span class="status <?= (int)$product['is_active']?'approved':'awaiting' ?>">
<?= (int)$product['is_active']?'Visible':'Hidden' ?>
</span>
</td>
<td class="actions">
<a href="?edit_product=<?= rawurlencode($product['id']) ?>">Edit</a>
<form method="post" action="actions.php" onsubmit="return confirm('Delete this product?')">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
<input type="hidden" name="return_view" value="products">
<input type="hidden" name="action" value="delete_product">
<input type="hidden" name="product_id" value="<?= adminEscape($product['id']) ?>">
<button>Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
<?php endif; ?>

<?php elseif($view === 'payments'): ?>
<section class="page-heading">
<div>
<p class="section-kicker">Payments</p>
<h2>
<?= $editingBank?'Edit bank account':'Payment accounts' ?>
</h2>
<p>Give customers clear, trusted instructions for bank transfers.</p>
</div>
</section>
<div class="payment-layout">
<section class="card form-card">
<h2>
<?= $editingBank?'Update account':'Add bank account' ?>
</h2>
<form method="post" action="actions.php">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
<input type="hidden" name="return_view" value="payments">
<input type="hidden" name="action" value="<?= $editingBank?'update_bank_account':'create_bank_account' ?>">
<?php if($editingBank): ?>
<input type="hidden" name="bank_id" value="<?= (int)$editingBank['id'] ?>">
<?php endif; ?>
<div class="form-grid">
<label>Bank name<input name="bank_name" value="<?= adminEscape($editingBank['bank_name']??'') ?>" required>
</label>
<label>Account name<input name="account_name" value="<?= adminEscape($editingBank['account_name']??'') ?>" required>
</label>
<label class="full">Account number<input name="account_number" value="<?= adminEscape($editingBank['account_number']??'') ?>" required>
</label>
<label class="full">Transfer instructions<textarea name="instructions">
<?= adminEscape($editingBank['instructions']??'') ?>
</textarea>
</label>
<label class="toggle-label">
<input type="checkbox" name="is_active" value="1" <?= !$editingBank||(int)$editingBank['is_active']===1?'checked':'' ?>>
<i>
</i>Enable this account</label>
</div>
<button class="primary-button" type="submit">✓ <?= $editingBank?'Save account':'Add account' ?>
</button>
</form>
</section>
<section class="card payment-insight">
<span>₦</span>
<h2>Payment status</h2>
<strong>
<?= $pending ?>
</strong>
<p>order<?= $pending===1?'':'s' ?> awaiting a payment or receipt review.</p>
<a href="?view=orders">Review orders <?= icon('arrow') ?>
</a>
</section>
</div>
<section class="card table-card">
<div class="card-heading">
<div>
<h2>Bank accounts</h2>
<p>Only active accounts are shown to customers at checkout.</p>
</div>
</div>
<?php if($accounts): ?>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Bank</th>
<th>Account holder</th>
<th>Account number</th>
<th>Status</th>
<th>
</th>
</tr>
</thead>
<tbody>
<?php foreach($accounts as $account): ?>
<tr>
<td>
<strong>
<?= adminEscape($account['bank_name']) ?>
</strong>
</td>
<td>
<?= adminEscape($account['account_name']) ?>
</td>
<td class="account-number">
<?= adminEscape($account['account_number']) ?>
</td>
<td>
<span class="status <?= (int)$account['is_active']?'approved':'awaiting' ?>">
<?= (int)$account['is_active']?'Active':'Hidden' ?>
</span>
</td>
<td class="actions">
<a href="?edit_bank=<?= (int)$account['id'] ?>">Edit</a>
<form method="post" action="actions.php" onsubmit="return confirm('Delete this bank account?')">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
<input type="hidden" name="return_view" value="payments">
<input type="hidden" name="action" value="delete_bank_account">
<input type="hidden" name="bank_id" value="<?= (int)$account['id'] ?>">
<button>Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state compact">
<strong>No payment account yet.</strong>
<p>Add a bank account so customers can complete bank transfers.</p>
</div>
<?php endif; ?>
</section>

<?php elseif($view === 'orders'): ?>
<section class="page-heading">
<div>
<p class="section-kicker">Transactions</p>
<h2>Orders</h2>
<p>Every customer order and its payment progress, in one place.</p>
</div>
<span class="select-pill">
<?= $countOrders ?> order<?= $countOrders===1?'':'s' ?>
</span>
</section>
<section class="card table-card">
<div class="card-heading">
<div>
<h2>Order activity</h2>
<p>Newest orders appear first.</p>
</div>
</div>
<?php if($orders): ?>
<div class="table-wrap">
<table>
<thead>
<tr>
<th>Order</th>
<th>Customer</th>
<th>Delivery</th>
<th>Date</th>
<th>Total</th>
<th>Payment status</th>
</tr>
</thead>
<tbody>
<?php foreach($orders as $order): ?>
<tr>
<td>
<strong>
<?= adminEscape($order['order_code']) ?>
</strong>
<small>
<?= adminEscape($order['payment_method']?:'No method selected') ?>
 · <?= ($order['storefront'] ?? 'aurahuz') === 'fitness' ? 'Aurahuz Fitness' : 'Aurahuz Fashion' ?>
</small>
</td>
<td>
<strong>
<?= adminEscape($order['customer_name']) ?>
</strong>
<small>
<?= adminEscape($order['customer_email']) ?>
<br>
<?= adminEscape($order['customer_phone']) ?>
</small>
</td>
<td>
<?= adminEscape($order['delivery_location']) ?>
</td>
<td>
<?= date('M j, Y',strtotime($order['created_at'])) ?>
<small>
<?= date('g:i a',strtotime($order['created_at'])) ?>
</small>
</td>
<td>
<strong>₦<?= number_format((float)$order['total'],2) ?>
</strong>
</td>
<td>
<?= orderStatus($order['payment_status']) ?>
<?php if($order['receipt_path']): ?>
<a class="receipt-link" href="receipt.php?order=<?= (int)$order['id'] ?>" target="_blank" rel="noopener">View receipt</a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state orders-empty">
<span>◌</span>
<strong>No orders to review yet.</strong>
<p>Once a shopper completes checkout, order details and payment status will be shown here.</p>
<a class="outline-button" href="../index.html">View fashion</a>
<a class="outline-button" href="../index-workout.html">View fitness</a>
</div>
<?php endif; ?>
</section>

<?php else: ?>
<section class="page-heading">
<div>
<p class="section-kicker">Workspace</p>
<h2>Settings</h2>
<p>Keep your admin workspace calm and tuned to the way you work.</p>
</div>
</section>
<section class="card settings-card">
<nav>
<button class="active">General</button>
<button>Notifications</button>
<button>Transaction report</button>
<button>Payments</button>
</nav>
<div>
<h2>Notifications</h2>
<p>Choose what you want to stay on top of while running Aurahuz.</p>
<?php foreach(['Payment receipts'=>'Get an email when a customer uploads a bank-transfer receipt.','New orders'=>'Be notified as soon as a customer starts their order.','Catalog reminders'=>'Receive a gentle reminder to review hidden products.'] as $label=>$copy): ?>
<div class="setting-row">
<span>
<strong>
<?= $label ?>
</strong>
<small>
<?= $copy ?>
</small>
</span>
<label class="switch">
<input type="checkbox" <?= $label==='Catalog reminders'?'':'checked' ?>>
<i>
</i>
</label>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>
</main>
</div>
<script>(()=>{const toggle=document.querySelector('[data-sidebar-toggle]'),close=document.querySelector('[data-sidebar-close]'),setOpen=open=>{document.body.classList.toggle('sidebar-open',open);toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Close navigation':'Open navigation')};toggle.addEventListener('click',()=>setOpen(!document.body.classList.contains('sidebar-open')));close.addEventListener('click',()=>setOpen(false));document.addEventListener('keydown',event=>{if(event.key==='Escape')setOpen(false)});document.querySelectorAll('#admin-sidebar a').forEach(link=>link.addEventListener('click',()=>setOpen(false));const productLayout=document.querySelector('.product-layout'),productTable=productLayout?.nextElementSibling;if(productTable?.classList.contains('table-card'))productLayout.before(productTable);})();</script>
</body>
</html>
