<?php
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['supplier_name'];
    $email = $_POST['supplier_email'];
    $phone = $_POST['supplier_phone'];
    $category_id = $_POST['category_id'];
    $reg_date = date('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, supplier_email, supplier_phone, category_id, reg_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssis", $name, $email, $phone, $category_id, $reg_date);
    $stmt->execute();

    header("Location: suppliers.php"); // redirect back
    exit;
}
?>
