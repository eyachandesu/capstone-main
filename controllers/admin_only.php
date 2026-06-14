<?php
// admin_only.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// 2. CHECK ROLE: If user is a CASHIER (Role ID 1)
if ($_SESSION['role_id'] == 1) {
    // Force them back to the Cashier Page
    header("Location: cashier_pos.php");
    exit();
}

// 3. Optional: If user is NOT an Admin (Role ID 2) either, block them
if ($_SESSION['role_id'] != 2) {
    echo "Access Denied. You do not have permission to view this page.";
    exit();
}
?>