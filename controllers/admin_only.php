<?php
// admin_only.php

require_once __DIR__ . '/../config/config.php';
// config.php already calls session_start() with a working save path -
// no need to call it again here.

// 1. Check if user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role_id'])) {
    header("Location: /login.php");
    exit();
}

$user_role = (int)$_SESSION['role_id'];

// 2. Block cashiers (role 1) from admin-only pages - send them to their
//    own area instead.
if ($user_role === 1) {
    header("Location: /cashier_pos.php");
    exit();
}

// 3. Block anyone who isn't a recognized admin role.
if ($user_role !== 2) {
    echo "Access Denied. You do not have permission to view this page.";
    exit();
}

// Role 2 (Admin) is authorized - fall through and let the page continue
// loading normally. No redirect here.