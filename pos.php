<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle = 'Point of Sale';
$error = '';

$products = $pdo->query("
    SELECT id, name, sku, unit, selling_price, stock_qty
    FROM products
    WHERE active = 1
      AND stock_qty > 0
      AND (expiration_date IS NULL OR expiration_date >= CURDATE())
    ORDER BY name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $cart = $_POST['cart'] ?? [];
    $cash = round((float) ($_POST['cash_received'] ?? 0), 2);
    $discount = round((float) ($_POST['discount'] ?? 0), 2);

    try {
        if (!is_array($cart) || !$cart) {
            throw new RuntimeException('Add at least one product to the cart.');
        }

        $pdo->beginTransaction();
        $items = [];
        $subtotal = 0;

        $findProduct = $pdo->prepare("
            SELECT id, name, cost_price, selling_price, stock_qty,
                   DATEDIFF(expiration_date, CURDATE()) AS days_left
            FROM products
            WHERE id = ? AND active = 1
            FOR UPDATE
        ");

        foreach ($cart as $productId => $rawQuantity) {
            $quantity = (float) $rawQuantity;
            if ($quantity <= 0) {
                continue;
            }

            $findProduct->execute([(int) $productId]);
            $product = $findProduct->fetch();

            if (!$product) {
                throw new RuntimeException('A selected product is no longer available.');
            }
            if ($product['days_left'] !== null && (int) $product['days_left'] < 0) {
                throw new RuntimeException($product['name'] . ' has expired and cannot be sold.');
            }
            if ($quantity > (float) $product['stock_qty']) {
                throw new RuntimeException('Not enough stock for ' . $product['name'] . '.');
            }

            $lineTotal = round($quantity * (float) $product['selling_price'], 2);
            $subtotal += $lineTotal;
            $items[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $lineTotal,
            ];
        }

        if (!$items) {
            throw new RuntimeException('Add at least one valid product.');
        }

        $subtotal = round($subtotal, 2);
        if ($discount < 0 || $discount > $subtotal) {
            throw new RuntimeException('Discount must be between ₱0 and the subtotal.');
        }

        $total = round($subtotal - $discount, 2);
        if ($cash < $total) {
            throw new RuntimeException('Cash received must cover the total amount.');
        }

        $transactionNo = 'SS' . date('ymdHis')
            . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

        $saveSale = $pdo->prepare("
            INSERT INTO sales (
                transaction_no, cashier_id, subtotal, discount,
                total_amount, cash_received, change_amount, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
        ");
        $saveSale->execute([
            $transactionNo, $_SESSION['user_id'], $subtotal, $discount,
            $total, $cash, round($cash - $total, 2),
        ]);
        $saleId = (int) $pdo->lastInsertId();

        $saveItem = $pdo->prepare("
            INSERT INTO sale_items (
                sale_id, product_id, product_name, quantity, unit_price, unit_cost, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $deductStock = $pdo->prepare("
            UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?
        ");
        $saveMovement = $pdo->prepare("
            INSERT INTO inventory_movements (
                product_id, user_id, movement_type, quantity_change,
                reference_type, reference_id, notes
            ) VALUES (?, ?, 'sale', ?, 'sale', ?, 'POS sale')
        ");

        foreach ($items as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];

            $saveItem->execute([
                $saleId, $product['id'], $product['name'], $quantity,
                $product['selling_price'], $product['cost_price'], $item['subtotal'],
            ]);
            $deductStock->execute([$quantity, $product['id']]);
            $saveMovement->execute([
                $product['id'], $_SESSION['user_id'], -$quantity, $saleId,
            ]);
        }

        log_action($pdo, 'create', 'sale', $saleId, $transactionNo);
        $pdo->commit();

        header('Location: sales.php?receipt=' . $saleId);
        exit;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($ex instanceof PDOException) {
            error_log('POS checkout failed: ' . $ex->getMessage());
            $error = 'Sale could not be saved. Please retry.';
        } else {
            $error = $ex->getMessage();
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="pos-layout"><section class="panel pos-products"><div class="panel-heading"><div><h3>Choose products</h3><p>Tap a product to add it to the cart.</p></div><span class="subtle-tag"><?= count($products) ?> available</span></div><input id="productSearch" class="search-wide" placeholder="Search products by name or SKU…"><div class="pos-product-grid" id="posProductGrid"><?php foreach($products as $p): ?><button type="button" class="pos-product" data-id="<?= $p['id'] ?>" data-name="<?= e($p['name']) ?>" data-sku="<?= e($p['sku']??'') ?>" data-price="<?= e((string)$p['selling_price']) ?>" data-stock="<?= e((string)$p['stock_qty']) ?>" data-unit="<?= e($p['unit']) ?>"><span class="pos-product-icon"><?= e(strtoupper(substr($p['name'],0,1))) ?></span><strong><?= e($p['name']) ?></strong><small><?= money($p['selling_price']) ?> / <?= e($p['unit']) ?></small><span class="stock-hint"><?= e((string)$p['stock_qty']) ?> <?= e($p['unit']) ?> available</span></button><?php endforeach; ?></div><p id="noProducts" class="muted hidden">No matching products found.</p></section>
<section class="panel cart-panel"><div class="panel-heading"><div><h3>Current sale</h3><p>Review items before checkout.</p></div><button type="button" id="clearCart" class="btn ghost small">Clear</button></div><?php if(!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post" id="checkoutForm"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div id="cartItems" class="cart-items"><div class="empty-state compact"><div>＋</div><strong>Your cart is empty</strong><p>Select products to start a sale.</p></div></div><div id="hiddenCart"></div><div class="cart-summary"><div><span>Subtotal</span><strong id="subtotalDisplay">₱0.00</strong></div><div class="discount-line"><label for="discount">Discount (₱)</label><input id="discount" name="discount" type="number" min="0" step="0.01" value="0"></div><div class="total-line"><span>Total due</span><strong id="totalDisplay">₱0.00</strong></div><label for="cashReceived">Cash received (₱)</label><input id="cashReceived" name="cash_received" type="number" min="0" step="0.01" required placeholder="Enter amount received"><div class="change-line"><span>Change</span><strong id="changeDisplay">₱0.00</strong></div><button class="btn primary full checkout-btn" type="submit">Complete sale <span>→</span></button><p class="form-help center">Stock is deducted only after the sale is saved successfully.</p></div></form></section></div>
<script src="assets/js/pos.js"></script>
<?php require __DIR__.'/includes/footer.php'; ?>
