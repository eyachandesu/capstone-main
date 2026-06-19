<?php
require_once __DIR__ . '/../config/conn.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("⚠️ Invalid request.");
}

$order_id   = intval($_POST['order_id'] ?? 0);
$stock_id   = intval($_POST['stock_id'] ?? 0);
$refund_qty = intval($_POST['refund_qty'] ?? 0);
$admin_id   = $_SESSION['admin_id'] ?? null;

if ($order_id <= 0 || $stock_id <= 0 || $refund_qty <= 0) {
    die("⚠️ Invalid refund request.");
}

// Get "Refunded" status
$status_sql = "SELECT order_status_id FROM order_status WHERE LOWER(order_status_name) = 'refunded' LIMIT 1";
$status_res = $conn->query($status_sql);
if (!$status_res || $status_res->num_rows === 0) {
    die("⚠️ 'Refunded' status not found.");
}
$refunded_status_id = $status_res->fetch_assoc()['order_status_id'];

$conn->begin_transaction();

try {
    // Fetch the specific item from this order
    $sql = "SELECT oi.id AS order_item_id, oi.qty, oi.price,
                   p.product_id, s.size_id, s.color_id
            FROM order_items oi
            INNER JOIN stock s ON oi.stock_id = s.stock_id
            INNER JOIN products p ON s.product_id = p.product_id
            WHERE oi.order_id = ? AND oi.stock_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $order_id, $stock_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) throw new Exception("Item not found in this order.");
    if ($refund_qty > $item['qty']) throw new Exception("Refund qty exceeds purchased qty.");

    $refund_amount = $refund_qty * $item['price'];

    // Insert refund
    $stmt = $conn->prepare("
        INSERT INTO refunds 
          (order_id, order_item_id, product_id, stock_id, size_id, color_id, refund_amount, refunded_at, refunded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $stmt->bind_param(
        "iiiiiidi",
        $order_id,
        $item['order_item_id'],
        $item['product_id'],
        $stock_id,
        $item['size_id'],
        $item['color_id'],
        $refund_amount,
        $admin_id
    );
    $stmt->execute();
    $stmt->close();

    // (Stock update and stock_in insert removed as per user request)

    // 👉 Order status logic:
    // If you want to mark the whole order refunded only if *all* items refunded, you need extra check here.
    $stmt = $conn->prepare("UPDATE orders SET order_status_id = ? WHERE order_id = ?");
    $stmt->bind_param("ii", $refunded_status_id, $order_id);
    $stmt->execute();
    $stmt->close();

    // Insert refund transaction for admin tracking
    $order_sql = "SELECT customer_id, payment_method_id FROM orders WHERE order_id = ?";
    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bind_param("i", $order_id);
    $order_stmt->execute();
    $order_data = $order_stmt->get_result()->fetch_assoc();
    $order_stmt->close();

    $trans_stmt = $conn->prepare("
        INSERT INTO transactions (order_id, customer_id, payment_method_id, total, order_status_id, date_time)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $trans_stmt->bind_param(
        "iiidi",
        $order_id,
        $order_data['customer_id'],
        $order_data['payment_method_id'],
        $refund_amount,
        $refunded_status_id
    );
    $trans_stmt->execute();
    $trans_stmt->close();

    $conn->commit();
    echo "<script>alert('✅ Refund successful!'); window.location.href='/public/pointofsale.php';</script>";
} catch (Exception $e) {
    $conn->rollback();
    die("❌ Refund failed: " . $e->getMessage());
}
