
<?php
require_once __DIR__ . '/../config/config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_data      = json_decode($_POST['cart_data'], true);
    $payment_method = intval($_POST['payment_method']);
    $cash_given     = floatval($_POST['cash_given']);
    $admin_id       = $_SESSION['admin_id'] ?? null;

    if (!$cart_data || count($cart_data) === 0) {
        die("Cart is empty.");
    }

    // ✅ Calculate total
    $total = 0;
    foreach ($cart_data as $item) {
        $total += $item['price'] * $item['qty'];
    }
    $change = $cash_given - $total;
    if ($change < 0) {
        die("Insufficient cash provided.");
    }

    $conn->begin_transaction();

    try {
        // 🔹 Get first product_id
        $first_stock_id = intval($cart_data[0]['stock_id']);
        $res = $conn->prepare("SELECT product_id FROM stock WHERE stock_id = ?");
        $res->bind_param("i", $first_stock_id);
        $res->execute();
        $first_product = $res->get_result()->fetch_assoc();
        $res->close();

        if (!$first_product) {
            throw new Exception("Invalid stock_id in cart.");
        }
        $first_product_id = $first_product['product_id'];

        // 🔹 Insert into orders
$stmt = $conn->prepare("
    INSERT INTO orders 
        (admin_id, total_amount, cash_given, changes, order_status_id, created_at, product_id, payment_method_id) 
    VALUES (?, ?, ?, ?, 0, NOW(), ?, ?)   -- ✅ Changed from 1 → 0
");
$stmt->bind_param("idddii", $admin_id, $total, $cash_given, $change, $first_product_id, $payment_method);
$stmt->execute();
$order_id = $stmt->insert_id;
$stmt->close();

// 🔹 Insert transaction (use walk-in customer by default = ID 1)
$customer_id = 1;
$stmt = $conn->prepare("
    INSERT INTO transactions 
        (order_id, customer_id, payment_method_id, total, order_status_id, date_time) 
    VALUES (?, ?, ?, ?, 2, NOW())   -- ✅ Changed from 1 → 2
");
$stmt->bind_param("iiid", $order_id, $customer_id, $payment_method, $total);
$stmt->execute();
$transaction_id = $stmt->insert_id;
$stmt->close();

        // 🔹 Items + stock update
        foreach ($cart_data as $item) {
            $stock_id = intval($item['stock_id']);
            $res = $conn->prepare("SELECT stock_id, product_id, current_qty FROM stock WHERE stock_id = ?");
            $res->bind_param("i", $stock_id);
            $res->execute();
            $stock = $res->get_result()->fetch_assoc();
            $res->close();

            if (!$stock || $stock['current_qty'] < $item['qty']) {
                throw new Exception("Not enough stock for " . $item['name']);
            }

            $stmt = $conn->prepare("INSERT INTO order_items (order_id, stock_id, qty, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $order_id, $stock_id, $item['qty'], $item['price']);
            $stmt->execute();
            $stmt->close();

            $new_qty = $stock['current_qty'] - $item['qty'];
            $stmt = $conn->prepare("UPDATE stock SET current_qty = ? WHERE stock_id = ?");
            $stmt->bind_param("ii", $new_qty, $stock_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE products SET stocks = IFNULL(stocks,0) - ? WHERE product_id = ?");
            $stmt->bind_param("ii", $item['qty'], $stock['product_id']);
            $stmt->execute();
            $stmt->close();
        }



        $conn->commit();

        // ✅ Thermal receipt output
        ?>
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>Receipt</title>
          <style>
            body { font-family: monospace, sans-serif; font-size: 12px; }
            .receipt { width: 260px; margin: auto; }
            h1 { text-align: center; font-size: 14px; margin: 5px 0; }
            .center { text-align: center; }
            .line { border-top: 1px dashed #000; margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 2px 0; }
            .right { text-align: right; }
            .footer { margin-top: 10px; text-align: center; font-size: 11px; }
          </style>
        </head>
        <body onload="window.print()">
          <div class="receipt">
            <h1>Seven Dwarfs POS</h1>
            <div class="center">
              Transaction #: <?= $transaction_id ?><br>
              Order ID: <?= $order_id ?><br>
              <?= date("Y-m-d H:i:s") ?>
            </div>
            <div class="line"></div>
            <table>
              <?php foreach ($cart_data as $item): ?>
                <tr>
                  <td><?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['size']) ?>/<?= htmlspecialchars($item['color']) ?>) x<?= $item['qty'] ?></td>
                  <td class="right">₱<?= number_format($item['price'] * $item['qty'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
            <div class="line"></div>
            <table>
              <tr><td>Total</td><td class="right">₱<?= number_format($total, 2) ?></td></tr>
              <tr><td>Cash</td><td class="right">₱<?= number_format($cash_given, 2) ?></td></tr>
              <tr><td>Change</td><td class="right">₱<?= number_format($change, 2) ?></td></tr>
            </table>
            <div class="line"></div>
            <div class="footer">
              Thank you for shopping!<br>
              Please come again
            </div>
          </div>
        </body>
        </html>
        <?php

    } catch (Exception $e) {
        $conn->rollback();
        die("Transaction failed: " . $e->getMessage());
    }
}
?>

