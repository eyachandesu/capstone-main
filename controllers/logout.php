<?php
session_start();
require_once __DIR__ . '/../config/conn.php';

// Check if user is logged in before trying to log database actions
if (isset($_SESSION["admin_id"])) {
    
    // 1. Get variables safely
    $user_id  = $_SESSION["admin_id"];
    $role_id  = $_SESSION["role_id"] ?? 0;         // Use 0 if not set
    $username = $_SESSION["username"] ?? 'Unknown'; // 🟢 CRITICAL: Database requires this

    // 2. Insert into system_logs
    // 🔴 OLD CODE ERROR: You were missing 'username' and trying to insert text into 'role_id'
    // 🟢 FIXED: Added username, and kept role_id as an integer
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, role_id, action) VALUES (?, ?, ?, 'Logout')");
    
    if ($stmt) {
        // Types: i = int, s = string, i = int
        $stmt->bind_param("isi", $user_id, $username, $role_id);
        $stmt->execute();
        $stmt->close();
    }

    // 3. Update last_logged_out
    $update = $conn->prepare("UPDATE adminusers SET last_logged_out = NOW() WHERE admin_id = ?");
    if ($update) {
        $update->bind_param("i", $user_id);
        $update->execute();
        $update->close();
    }
}

// 4. DESTROY SESSION (This will now run even if the DB fails)
$_SESSION = []; // Empty the array
session_unset();
session_destroy();

// 5. Redirect
header("Location: login.php");
exit;
?>