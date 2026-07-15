<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin_only.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: ../public/categories.php");
        exit();
    }

    if (!isset($conn)) {
        throw new Exception("Database connection not found.");
    }

    $category_name = trim($_POST['category_name'] ?? '');

    if (empty($category_name)) {
        $_SESSION['message'] = "Category name is required.";
        header("Location: ../public/categories.php");
        exit();
    }

    // Check duplicate category
    $check = $conn->prepare("
        SELECT category_id
        FROM categories
        WHERE category_name = ?
    ");

    if (!$check) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $check->bind_param("s", $category_name);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['message'] = "Category already exists.";
        $check->close();

        header("Location: /categories.php");
        exit();
    }

    $check->close();

    // Generate next category code
    $result = $conn->query("
        SELECT IFNULL(MAX(category_id),0) + 1 AS next_id
        FROM categories
    ");

    $row = $result->fetch_assoc();

    $category_code = str_pad($row['next_id'], 3, "0", STR_PAD_LEFT);

    // Insert category
    $stmt = $conn->prepare("
        INSERT INTO categories
        (category_code, category_name)
        VALUES (?, ?)
    ");

    if (!$stmt) {
        throw new Exception("Insert Prepare Failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $category_code, $category_name);

    if ($stmt->execute()) {

        $_SESSION['success'] = "Category added successfully.";

    } else {

        $_SESSION['message'] = "Failed to add category.";
    }

    $stmt->close();

} catch (Exception $e) {

    die("<h2>ERROR</h2><pre>" . $e->getMessage() . "</pre>");

}

header("Location: /categories.php");
exit();