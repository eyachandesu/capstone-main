<?php
require_once __DIR__ . '/../controllers/admin_only.php';
require_once __DIR__ . '/../config/conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['category_name'])) {
    $category_name = trim($_POST['category_name']);

    if (!empty($category_name)) {
        // Auto-generate category_code based on last record
        $code_result = mysqli_query($conn, "SELECT category_code FROM categories ORDER BY category_id DESC LIMIT 1");
        $last_code = mysqli_fetch_assoc($code_result)['category_code'] ?? '000';
        $new_code = str_pad((int)$last_code + 1, 3, '0', STR_PAD_LEFT);

        $stmt = mysqli_prepare($conn, "INSERT INTO categories (category_code, category_name) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ss', $new_code, $category_name);
        mysqli_stmt_execute($stmt);
    }
}

header("Location: categories.php");
exit;
