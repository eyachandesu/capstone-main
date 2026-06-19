<?php
// admin_only.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role_id'])) {
    header("Location: login.php");
    exit();
}

$user_role = (int)$_SESSION['role_id'];

// 2. CHECK ROLE PERMISSIONS
if ($user_role === 1) {
    // Role 1 is Cashier -> Kick them out to the Cashier page
    header("Location: cashier_pos.php");
    exit();
} elseif ($user_role === 2) {
    header("Location: dashboard.php");
    exit();
} else {
    // Any other unknown role -> Block access
    echo "Access Denied. You do not have permission to view this page.";
    exit();
}
?>