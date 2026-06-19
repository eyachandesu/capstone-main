<?php
require_once __DIR__ . '/../config/conn.php';
session_start();

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $category_id = intval($_POST['category_id']);
    $category_name = trim($_POST['category_name']);

    if (empty($category_name) || !$category_id) {
        echo "<script>alert('Invalid input!'); window.location.href='/public/categories.php';</script>";
        exit;
    }

    // Fetch the old category name before update (for logging)
    $oldQuery = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
    $oldQuery->bind_param("i", $category_id);
    $oldQuery->execute();
    $oldResult = $oldQuery->get_result();
    $oldCategory = $oldResult->fetch_assoc()['category_name'] ?? '';
    $oldQuery->close();

    // Update category name
    $stmt = $conn->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
    $stmt->bind_param("si", $category_name, $category_id);

    if ($stmt->execute()) {
        // Log the action
        if (isset($_SESSION['admin_id'])) {
            $admin_id = $_SESSION['admin_id'];
            $log = $conn->prepare("INSERT INTO system_logs (user_id, username, role_id, action) 
                                   VALUES (?, ?, ?, ?)");
            $username = $_SESSION['username'] ?? 'Unknown';
            $role_id = $_SESSION['role_id'] ?? 0;
            $action = "Updated category from '$oldCategory' to '$category_name'";
            $log->bind_param("isis", $admin_id, $username, $role_id, $action);
            $log->execute();
            $log->close();
        }

        echo "<script>alert('Category updated successfully!'); window.location.href='/public/categories.php';</script>";
    } else {
        echo "<script>alert('Error updating category. Please try again.'); window.location.href='/public/categories.php';</script>";
    }

    $stmt->close();
}
$conn->close();
?>
