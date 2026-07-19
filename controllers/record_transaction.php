<?php
require_once __DIR__ . '/../config/config.php';

// Get the JSON payload from the request body
$data = json_decode(file_get_contents('php://input'), true);

// Check if the required fields are available
if (!isset($data['adminId'], $data['totalAmount'], $data['items'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$adminId = $data['adminId'];  // Changed to adminId
$totalAmount = $data['totalAmount'];
$items = $data['items'];
$cashReceived = $data['cashReceived'];  // Expecting cashReceived to be sent in the payload
$changeAmount = $data['changes'];  // Expecting changes to be sent in the payload

// Start a transaction to ensure consistency
$conn->begin_transaction();

try {
    // Insert the order into the orders table
    $stmt = $conn->prepare("INSERT INTO orders (admin_id, total_amount, cash_given, changes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iddd", $adminId, $totalAmount, $cashReceived, $changeAmount);
    $stmt->execute();
    $orderId = $stmt->insert_id;

    // Insert each item into the order_items table
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_id, discount, total) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($items as $item) {
        $stmt->bind_param("iiiddd", $orderId, $item['product_id'], $item['quantity'], $item['price'], $item['discount'], $item['total']);
        $stmt->execute();
    }

    // Commit the transaction
    $conn->commit();

    // Return success response with the new order ID
    echo json_encode(['success' => true, 'orderId' => $orderId]);

} catch (Exception $e) {
    // Rollback the transaction in case of error
    $conn->rollback();

    echo json_encode(['success' => false, 'error' => 'Transaction failed']);
}
