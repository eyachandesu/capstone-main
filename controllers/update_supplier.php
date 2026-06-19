<?php
require_once __DIR__ . '/../config/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['supplier_id']);
    $name = trim($_POST['supplier_name']);
    $email = trim($_POST['supplier_email']);
    $phone = trim($_POST['supplier_phone']);
    $category_id = intval($_POST['category_id']);

    $stmt = $conn->prepare("UPDATE suppliers SET supplier_name=?, supplier_email=?, supplier_phone=?, category_id=? WHERE supplier_id=?");
    $stmt->bind_param("sssii", $name, $email, $phone, $category_id, $id);

    if ($stmt->execute()) {
        header("Location: /public/suppliers.php");
        exit();
    } else {
        echo "Error updating supplier: " . $stmt->error;
    }
}
?>
