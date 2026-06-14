<?php
require_once __DIR__ . '/../config/conn.php';
$order_id = intval($_GET['order_id'] ?? 0);

$sql = "
    SELECT oi.id AS order_item_id, oi.stock_id, oi.qty, oi.price,
           p.product_name, col.color, sz.size
    FROM order_items oi
    INNER JOIN stock s ON oi.stock_id = s.stock_id
    INNER JOIN products p ON s.product_id = p.product_id
    LEFT JOIN colors col ON s.color_id = col.color_id
    LEFT JOIN sizes sz ON s.size_id = sz.size_id
    WHERE oi.order_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

header('Content-Type: application/json');
echo json_encode($items);
