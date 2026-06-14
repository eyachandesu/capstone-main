<?php
session_start();
require_once __DIR__ . '/../config/conn.php';

// ✅ Only Admin (role_id = 2) can toggle user status
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 2) {
    $_SESSION['message'] = "Unauthorized access.";
    header("Location: manage_users.php");
    exit();
}

// ✅ Ensure valid parameters
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['message'] = "Invalid request.";
    header("Location: manage_users.php");
    exit();
}

$target_id = (int) $_GET['id'];
$new_status = (int) $_GET['status'];
$current_admin_id = $_SESSION['admin_id'];

// 🧩 Prevent Admin from deactivating their own account
if ($target_id === $current_admin_id) {
    $_SESSION['message'] = "You cannot deactivate your own account.";
    header("Location: manage_users.php");
    exit();
}

// 🧩 Get target user role to prevent toggling other Admins
$roleCheck = $conn->prepare("SELECT role_id FROM adminusers WHERE admin_id = ?");
$roleCheck->bind_param("i", $target_id);
$roleCheck->execute();
$roleResult = $roleCheck->get_result();

if ($roleResult->num_rows === 0) {
    $_SESSION['message'] = "User not found.";
    header("Location: manage_users.php");
    exit();
}

$target = $roleResult->fetch_assoc();
$target_role_id = $target['role_id'];
$roleCheck->close();

// 🚫 Prevent Admin from deactivating another Admin
if ($target_role_id == 2) {
    $_SESSION['message'] = "You cannot activate/deactivate another Admin.";
    header("Location: manage_users.php");
    exit();
}

// ✅ Check if new status exists
$statusCheck = $conn->prepare("SELECT status_name FROM status WHERE status_id = ?");
$statusCheck->bind_param("i", $new_status);
$statusCheck->execute();
$statusResult = $statusCheck->get_result();

if ($statusResult->num_rows === 0) {
    $_SESSION['message'] = "Invalid status.";
    header("Location: manage_users.php");
    exit();
}

$statusRow = $statusResult->fetch_assoc();
$statusName = $statusRow['status_name'];
$statusCheck->close();

// ✅ Update user status
$stmt = $conn->prepare("UPDATE adminusers SET status_id = ? WHERE admin_id = ?");
$stmt->bind_param("ii", $new_status, $target_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "User status changed to '{$statusName}' successfully.";
} else {
    $_SESSION['message'] = "Error updating status: " . $stmt->error;
}

$stmt->close();
$conn->close();

header("Location: manage_users.php");
exit();
