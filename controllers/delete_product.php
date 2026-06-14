<?php
session_start();
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/conn.php';

// ✅ Require admin session
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid product ID!'); window.location.href='products.php';</script>";
    exit;
}

$product_id = intval($_GET['id']);

// ✅ Fetch product image list
$stmt = $conn->prepare("SELECT image_url FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$res = $stmt->get_result();
$product = $res->fetch_assoc();
$stmt->close();

$imageList = [];
if (!empty($product['image_url'])) {
    $raw = trim($product['image_url']);
    if ($raw && str_starts_with($raw, '[')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $imageList = $decoded;
    } else {
        $imageList = array_filter(array_map('trim', explode(',', $raw)));
    }
}

// ✅ Step 1: Get related stock IDs
$stockIDs = [];
$getStock = $conn->prepare("SELECT stock_id FROM stock WHERE product_id = ?");
$getStock->bind_param("i", $product_id);
$getStock->execute();
$resStock = $getStock->get_result();
while ($row = $resStock->fetch_assoc()) {
    $stockIDs[] = $row['stock_id'];
}
$getStock->close();

// ✅ Step 2: Delete from order_items, refunds, stock_in for each stock_id
if (!empty($stockIDs)) {
    foreach ($stockIDs as $sid) {
        // Delete refunds linked to this stock
        $conn->query("DELETE FROM refunds WHERE stock_id = $sid");
        // Delete order_items linked to this stock
        $conn->query("DELETE FROM order_items WHERE stock_id = $sid");
        // Delete stock_in linked to this stock
        $conn->query("DELETE FROM stock_in WHERE stock_id = $sid");
    }
}

// ✅ Step 3: Delete stocks related to this product
$delStock = $conn->prepare("DELETE FROM stock WHERE product_id = ?");
$delStock->bind_param("i", $product_id);
$delStock->execute();
$delStock->close();

// ✅ Step 4: Delete product_colors and product_sizes
$conn->query("DELETE FROM product_colors WHERE product_id = $product_id");
$conn->query("DELETE FROM product_sizes WHERE product_id = $product_id");

// ✅ Step 5: Delete the product itself
$delProd = $conn->prepare("DELETE FROM products WHERE product_id = ?");
$delProd->bind_param("i", $product_id);
$success = $delProd->execute();
$delProd->close();

// ✅ Step 6: Delete image files
if ($success) {
    foreach ($imageList as $img) {
        $path = 'uploads/products/' . basename($img);
        if (file_exists($path)) unlink($path);
    }
    echo "<script>alert('Product deleted successfully!'); window.location.href='products.php';</script>";
} else {
    echo "<script>alert('Failed to delete product.'); window.location.href='products.php';</script>";
}

$conn->close();
?>
