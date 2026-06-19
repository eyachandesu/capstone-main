<?php
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/conn.php';
include 'auth_session.php';

// 🧩 Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: /public/suppliers.php?error=InvalidSupplierID");
    exit;
}

$supplier_id = intval($_GET['id']);

// 🧩 Verify supplier exists before deletion
$check = $conn->prepare("SELECT supplier_name FROM suppliers WHERE supplier_id = ?");
$check->bind_param("i", $supplier_id);
$check->execute();
$result = $check->get_result();
$supplier = $result->fetch_assoc();
$check->close();

if (!$supplier) {
    header("Location: /public/suppliers.php?error=SupplierNotFound");
    exit;
}

// 🗑️ Delete supplier safely
$stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: /public/suppliers.php?deleted=1");
    exit;
} else {
    $stmt->close();
    header("Location: /public/suppliers.php?error=DeleteFailed");
    exit;
}
?>
