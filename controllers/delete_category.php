<?php
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/conn.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $category_id = intval($_POST['category_id']);

    if (!$category_id) {
        echo "<script>alert('Invalid category ID!'); window.location.href='/public/categories.php';</script>";
        exit;
    }

    // Get the category name before deleting (for logging)
    $query = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
    $query->bind_param("i", $category_id);
    $query->execute();
    $result = $query->get_result();
    $category = $result->fetch_assoc();
    $category_name = $category['category_name'] ?? 'Unknown Category';
    $query->close();

    // Delete the category
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);

    if ($stmt->execute()) {
        // Log the deletion to system_logs
        if (isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];
            $username = $_SESSION['username'] ?? 'Unknown';
            $role_id = $_SESSION['role_id'] ?? 0;
            $action = "Deleted category '$category_name' (ID: $category_id)";

            $log = $conn->prepare("INSERT INTO system_logs (user_id, username, role_id, action) 
                                   VALUES (?, ?, ?, ?)");
            $log->bind_param("isis", $admin_id, $username, $role_id, $action);
            $log->execute();
            $log->close();
        }

        echo "<script>alert('Category deleted successfully!'); window.location.href='/public/categories.php';</script>";
    } else {
        echo "<script>alert('Error deleting category. Please try again.'); window.location.href='/public/categories.php';</script>";
    }

    $stmt->close();
}
$conn->close();
?>
