<?php
require_once __DIR__ . '/../config/config.php';
session_start();

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get Data
    $product_id  = intval($_POST['product_id']);
    $quantity    = intval($_POST['quantity']);
    $supplier_id = intval($_POST['supplier_id']);

    // 🟢 Handle Optional Color/Size (Convert empty to NULL, otherwise Integer)
    $color_id = !empty($_POST['color_id']) ? intval($_POST['color_id']) : null;
    $size_id  = !empty($_POST['size_id']) ? intval($_POST['size_id']) : null;
    
    // Get Prices
    $supplier_price = isset($_POST['supplier_price']) ? floatval($_POST['supplier_price']) : 0.00;
    $selling_price  = isset($_POST['price']) ? floatval($_POST['price']) : 0.00;

    // 🟢 Validate inputs (Removed checks for color_id and size_id)
    if ($product_id <= 0 || $quantity <= 0 || $supplier_id <= 0) {
        die("Invalid input. Product, Quantity, and Supplier are required.");
    }

    // 2️⃣ Update Product Prices (If changed)
    if ($product_id > 0) {
        $updatePrices = $conn->prepare("
            UPDATE products 
            SET supplier_price = ?, price_id = ?, supplier_id = ? 
            WHERE product_id = ?
        ");
        $updatePrices->bind_param("ddii", $supplier_price, $selling_price, $supplier_id, $product_id);
        $updatePrices->execute();
        $updatePrices->close();
    }

    // 3️⃣ Check if stock entry exists
    // 🟢 USE `<=>` (NULL-SAFE EQUALITY)
    // Standard SQL '=' returns null if comparing to null. '<=>' returns true if both are null.
    $checkStock = $conn->prepare("
        SELECT stock_id, current_qty 
        FROM stock 
        WHERE product_id = ? 
        AND color_id <=> ? 
        AND size_id <=> ?
        LIMIT 1
    ");
    $checkStock->bind_param("iii", $product_id, $color_id, $size_id);
    $checkStock->execute();
    $result = $checkStock->get_result();

    if ($row = $result->fetch_assoc()) {
        // 4️⃣ Update existing stock quantity
        $stock_id = $row['stock_id'];
        $new_qty = $row['current_qty'] + $quantity;

        $updateStock = $conn->prepare("UPDATE stock SET current_qty = ? WHERE stock_id = ?");
        $updateStock->bind_param("ii", $new_qty, $stock_id);
        $updateStock->execute();
        $updateStock->close();
    } else {
        // 5️⃣ Insert a new stock record (Passing NULL if allowed)
        $insertStock = $conn->prepare("
            INSERT INTO stock (product_id, color_id, size_id, current_qty) 
            VALUES (?, ?, ?, ?)
        ");
        $insertStock->bind_param("iiii", $product_id, $color_id, $size_id, $quantity);
        $insertStock->execute();
        $stock_id = $insertStock->insert_id;
        $insertStock->close();
    }
    $checkStock->close();

    // 6️⃣ Record the stock-in transaction
    $insertStockIn = $conn->prepare("
        INSERT INTO stock_in (stock_id, supplier_id, quantity, date_added, purchase_price) 
        VALUES (?, ?, ?, NOW(), ?)
    ");
    // Added purchase_price to stock_in history for reporting accuracy
    $insertStockIn->bind_param("iiid", $stock_id, $supplier_id, $quantity, $supplier_price);
    $insertStockIn->execute();
    $insertStockIn->close();

    // 7️⃣ Update total product-level stock tracking (Product table sum)
    $updateProductStock = $conn->prepare("
        UPDATE products 
        SET stocks = COALESCE(stocks, 0) + ? 
        WHERE product_id = ?
    ");
    $updateProductStock->bind_param("ii", $quantity, $product_id);
    $updateProductStock->execute();
    $updateProductStock->close();

    // 8️⃣ Log Action
    $admin_id = $_SESSION['admin_id'];
    $admin_username = $_SESSION['username'] ?? 'Admin'; // Assuming session has username
    $action = "Added Stock (Qty: $quantity) for Product ID: $product_id";
    
    $logStmt = $conn->prepare("INSERT INTO system_logs (user_id, username, role_id, action) VALUES (?, ?, 2, ?)");
    $logStmt->bind_param("iss", $admin_id, $admin_username, $action);
    $logStmt->execute();
    $logStmt->close();

    // 9️⃣ Redirect back
    header("Location: /stock_management.php?success=1");
exit();
} else {
    die("Invalid request method.");
}
?>