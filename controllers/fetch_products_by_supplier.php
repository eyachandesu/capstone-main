<?php
require_once __DIR__ . '/../config/conn.php';

// Tell the browser we are sending JSON data
header('Content-Type: application/json');

if (isset($_GET['supplier_id'])) {
    $supplier_id = intval($_GET['supplier_id']);
    
    // 🟢 We fetch the product_id, name, AND supplier_price
    $stmt = $conn->prepare("
        SELECT product_id, product_name, supplier_price 
        FROM products 
        WHERE supplier_id = ? 
        ORDER BY product_name ASC
    ");
    
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    echo json_encode($products);
    $stmt->close();
}
?>