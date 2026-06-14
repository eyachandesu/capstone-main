<?php
session_start();
require_once __DIR__ . '/../config/conn.php';

// ✅ Restrict access to Super Admin only
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = intval($_POST['admin_id']);
    $new_role_id = intval($_POST['role_id']);


    // Update role
    $stmt = $conn->prepare("UPDATE adminusers SET role_id = ? WHERE admin_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $new_role_id, $admin_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "✅ User role updated successfully!";
        } else {
            $_SESSION['message'] = "❌ Error updating role: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "❌ Database error: " . $conn->error;
    }

    header("Location: manage_roles.php");
    exit();
} else {
    header("Location: manage_roles.php");
    exit();
}
?>
