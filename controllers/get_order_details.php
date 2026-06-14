<?php
require_once __DIR__ . '/../config/conn.php';
$order_id = intval($_GET['order_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.product_name, oi.qty, oi.price
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table class='w-full text-sm border-collapse'>
            <tr class='border-b'><th class='text-left px-2 py-1'>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr>";
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $t = $row['qty'] * $row['price'];
        $total += $t;
        echo "<tr class='border-b'>
                <td class='px-2 py-1'>{$row['product_name']}</td>
                <td class='text-center'>{$row['qty']}</td>
                <td class='text-center'>₱" . number_format($row['price'], 2) . "</td>
                <td class='text-center'>₱" . number_format($t, 2) . "</td>
              </tr>";
    }
    echo "<tr><td colspan='3' class='text-right font-semibold py-2'>Total:</td><td class='text-center text-[var(--rose)] font-semibold'>₱" . number_format($total, 2) . "</td></tr></table>";
} else {
    echo "<p>No items found for this order.</p>";
}
$stmt->close();
$conn->close();
?>
