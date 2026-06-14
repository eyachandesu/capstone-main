<?php
require_once __DIR__ . '/../config/conn.php';
session_start();

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $color = $_POST['color'] ?? null;
    $size = $_POST['size'] ?? null;

    if ($product_id > 0 && $color && $size) {
        // 1️⃣ Get the stock_id for this product + color + size
        $query = "
            SELECT st.stock_id
            FROM stock st
            INNER JOIN colors c ON st.color_id = c.color_id
            INNER JOIN sizes s ON st.size_id = s.size_id
            WHERE st.product_id = ? AND c.color = ? AND s.size = ?
            LIMIT 1
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $product_id, $color, $size);
        $stmt->execute();
        $result = $stmt->get_result();
        $stock = $result->fetch_assoc();
        $stmt->close();

        if ($stock) {
            $stock_id = $stock['stock_id'];

            // 2️⃣ Delete related stock_in history first
            $deleteStockIn = $conn->prepare("DELETE FROM stock_in WHERE stock_id = ?");
            $deleteStockIn->bind_param("i", $stock_id);
            $deleteStockIn->execute();
            $deleteStockIn->close();

            // 3️⃣ Delete the stock entry itself
            $deleteStock = $conn->prepare("DELETE FROM stock WHERE stock_id = ?");
            $deleteStock->bind_param("i", $stock_id);
            $deleteStock->execute();
            $deleteStock->close();
        }
    }

    // ✅ Redirect back with success flag
    header("Location: stock_management.php?deleted=1");
    exit;
} else {
    die("Invalid request.");
}
