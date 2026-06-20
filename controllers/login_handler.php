<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../init.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /public/login.php", true, 303);
    exit();
}

// ✅ FIX 1: Read 'username' instead of 'login' to match your HTML form
$login_input = trim($_POST["username"] ?? '');
$password = $_POST["password"] ?? '';

if ($login_input === '' || $password === '') {
    setValidation("error", "Please enter login credentials.");
    header("Location: /public/login.php", true, 303);
    exit();
}

try {
    /* ================= ADMIN ================= */
    $sql = "SELECT admin_id, username, admin_email, password_hash, role_id, status_id
            FROM adminusers
            WHERE username = ? OR admin_email = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $login_input, $login_input);
    $stmt->execute();
    $result = $stmt->get_result();

    $user = $result->fetch_assoc();
    $type = "admin";

    /* ================= CUSTOMER ================= */
    if (!$user) {
        $sql = "SELECT customer_id, email, password_hash, status_id
                FROM customers
                WHERE email = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $login_input);
        $stmt->execute();
        $result = $stmt->get_result();

        $user = $result->fetch_assoc();
        $type = "customer";
    }

    /* ================= NOT FOUND ================= */
    if (!$user) {
        setValidation("error", "Account not found.");
        header("Location: /public/login.php", true, 303);
        exit();
    }

    /* ================= PASSWORD ================= */
    if (!password_verify($password, $user["password_hash"])) {
        setValidation("error", "Invalid password.");
        header("Location: /public/login.php", true, 303);
        exit();
    }

    /* ================= STATUS ================= */
    if ((int) $user["status_id"] !== 1) {
        setValidation("error", "Account is deactivated.");
        header("Location: /public/login.php", true, 303);
        exit();
    }

    /* ================= FIXED PAYLOAD ================= */
    $payload = [
        "user_id" => (int) ($user["admin_id"] ?? $user["customer_id"]),
        "username" => $user["username"] ?? $user["admin_email"] ?? $user["email"],
        "role" => $type,
        "role_id" => $user["role_id"] ?? null
    ];

    while (ob_get_level()) {
        ob_end_clean();
    }

    session_regenerate_id(true);

    // Build JWT
    $jwt = JwtHelper::generateToken($payload);
    setcookie("auth_token", "", time() - 3600, "/");
    setcookie("auth_token", $jwt, [
        "expires" => time() + 3600,
        "path" => "/",
        "httponly" => true,
        "secure" => false,
        "samesite" => "Lax"
    ]);

    // ✅ FIX 2: Removed the hardcoded dashboard redirect here. 
    // Now the logic splits safely down below based on the role profile.

    /* ================= REDIRECT ================= */
    if ($type === "admin") {
        header("Location: /public/dashboard.php", true, 303);
    } else {
        header("Location: /public/customerside/homepage.php", true, 303);
    }
    exit();

} catch (Exception $e) {
    error_log($e->getMessage());
    setValidation("error", "System error occurred.");
    header("Location: /public/login.php", true, 303);
    exit();
}