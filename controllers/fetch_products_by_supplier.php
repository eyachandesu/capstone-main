<?php

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_GET['supplier_id']) || empty($_GET['supplier_id'])) {
    echo json_encode([]);
    exit;
}

$supplier_id = (int)$_GET['supplier_id'];

$sql = "
SELECT
    product_id,
    product_name,
    supplier_price,
    price_id
FROM products
WHERE supplier_id = ?
ORDER BY product_name
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();

$result = $stmt->get_result();

$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);