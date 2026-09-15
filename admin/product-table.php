<section class="card table-card">
  <div class="card-heading">
    <div>
      <h2>Products</h2>
      <p>Manage the collection in your storefront.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Store</th><th>Price</th><th>Badge</th><th>Visibility</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($products as $product): ?>
          <tr>
            <td class="product-cell"><img src="../<?= adminEscape($product['image']) ?>" alt=""><span><strong><?= adminEscape($product['name']) ?></strong><small><?= adminEscape($product['id']) ?></small></span></td>
            <td><?= $product['storefront'] === 'fitness' ? 'Aurahuz Fitness' : 'Aurahuz Fashion' ?></td>
            <td>₦<?= number_format((float) $product['price'], 2) ?></td>
            <td><?= adminEscape($product['tag'] ?: '—') ?></td>
            <td><span class="status <?= (int) $product['is_active'] ? 'approved' : 'awaiting' ?>"><?= (int) $product['is_active'] ? 'Visible' : 'Hidden' ?></span></td>
            <td class="actions"><a href="?edit_product=<?= rawurlencode($product['id']) ?>">Edit</a><form method="post" action="actions.php" onsubmit="return confirm('Delete this product?')"><input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"><input type="hidden" name="return_view" value="products"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="product_id" value="<?= adminEscape($product['id']) ?>"><button>Delete</button></form></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
