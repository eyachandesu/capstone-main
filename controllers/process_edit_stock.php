<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../config/config.php';

/* -----------------------------
   Check Login
------------------------------ */

if (!isset($_SESSION['admin_id'])) {
    header("Location: /login.php");
    exit();
}

/* -----------------------------
   Request Validation
------------------------------ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /stock_management.php");
    exit();
}

/* -----------------------------
   Get Form Values
------------------------------ */

$stock_id = intval($_POST['stock_id'] ?? 0);
$product_id = intval($_POST['product_id'] ?? 0);

$current_qty = intval($_POST['new_quantity'] ?? 0);

$color_id = !empty($_POST['color_id'])
    ? intval($_POST['color_id'])
    : null;

$size_id = !empty($_POST['size_id'])
    ? intval($_POST['size_id'])
    : null;

$supplier_id = intval($_POST['supplier_id'] ?? 0);

$supplier_price = floatval($_POST['supplier_price'] ?? 0);

$selling_price = floatval($_POST['price'] ?? 0);

/* -----------------------------
   Validation
------------------------------ */

if ($stock_id <= 0) {
    die("Invalid Stock ID.");
}

if ($product_id <= 0) {
    die("Invalid Product.");
}

if ($current_qty < 0) {
    die("Invalid Quantity.");
}

/* -----------------------------
   Start Transaction
------------------------------ */

$conn->begin_transaction();

try {

    /* -------------------------
       Check Stock Exists
    -------------------------- */

    $check = $conn->prepare("
        SELECT stock_id
        FROM stock
        WHERE stock_id = ?
    ");

    $check->bind_param("i", $stock_id);
    $check->execute();

    if ($check->get_result()->num_rows == 0) {
        throw new Exception("Stock record not found.");
    }

    $check->close();

    /* -------------------------
       Update Stock
    -------------------------- */

    $sql = "
        UPDATE stock
        SET
            current_qty = ?,
            color_id = ?,
            size_id = ?,
            product_id = ?
        WHERE stock_id = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "iiiii",
        $current_qty,
        $color_id,
        $size_id,
        $product_id,
        $stock_id
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();

    /* -------------------------
       Update Product Prices
    -------------------------- */

    $sql = "
        UPDATE products
        SET
            supplier_price = ?,
            price_id = ?,
            supplier_id = ?
        WHERE product_id = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ddii",
        $supplier_price,
        $selling_price,
        $supplier_id,
        $product_id
    );

    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $stmt->close();

    /* -------------------------
       Recalculate Total Stocks
    -------------------------- */

    $sum = $conn->prepare("
        SELECT COALESCE(SUM(current_qty),0) total_qty
        FROM stock
        WHERE product_id = ?
    ");

    $sum->bind_param("i", $product_id);
    $sum->execute();

    $total = $sum->get_result()->fetch_assoc()['total_qty'];

    $sum->close();

    $update = $conn->prepare("
        UPDATE products
        SET stocks = ?
        WHERE product_id = ?
    ");

    $update->bind_param(
        "ii",
        $total,
        $product_id
    );

    $update->execute();
    $update->close();

    /* -------------------------
       Log Action
    -------------------------- */

    $admin_id = $_SESSION['admin_id'];
    $username = $_SESSION['username'] ?? 'Admin';

    $action = "Edited Stock ID {$stock_id}";

    $log = $conn->prepare("
        INSERT INTO system_logs
        (user_id, username, role_id, action)
        VALUES (?, ?, 2, ?)
    ");

    if ($log) {
        $log->bind_param(
            "iss",
            $admin_id,
            $username,
            $action
        );

        $log->execute();
        $log->close();
    }

    $conn->commit();

    header("Location: /stock_management.php?success=1");
    exit();

} catch (Exception $e) {

    $conn->rollback();

    die($e->getMessage());
}