<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helper/jwt_helper.php';
require_once __DIR__ . '/../helper/validation.php';
// config.php already calls session_start() with a working save path.

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /login.php", true, 303);
    exit();
}

/* ================= CSRF CHECK ================= */
// Assumes a token was embedded in the login form as a hidden field
// named "csrf_token", and that it was set into $_SESSION["csrf_token"]
// when the login form was rendered. If login.php doesn't do this yet,
// remove this block until it does, or logins will always fail here.
if (
    empty($_POST["csrf_token"]) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
) {
    setValidation("error", "Invalid or expired form submission. Please try again.");
    header("Location: /login.php", true, 303);
    exit();
}

/* ================= BASIC RATE LIMITING ================= */
// Simple session-based throttle: max 5 attempts per 60 seconds.
$_SESSION["login_attempts"] = $_SESSION["login_attempts"] ?? [];
$now = time();
$_SESSION["login_attempts"] = array_filter(
    $_SESSION["login_attempts"],
    fn($ts) => $ts > $now - 60
);

if (count($_SESSION["login_attempts"]) >= 5) {
    setValidation("error", "Too many login attempts. Please wait a minute and try again.");
    header("Location: /login.php", true, 303);
    exit();
}

// Get login credentials
$login_input = trim($_POST["username"] ?? '');
$password = $_POST["password"] ?? '';

if ($login_input === '' || $password === '') {
    setValidation("error", "Please enter login credentials.");
    header("Location: /login.php", true, 303);
    exit();
}

// Generic message used everywhere to avoid leaking which accounts exist
const LOGIN_FAIL_MSG = "Invalid username or password.";

try {

    /* ================= ADMIN ================= */

    $sql = "SELECT admin_id, username, admin_email, password_hash, role_id, status_id
            FROM adminusers
            WHERE username = ? OR admin_email = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Failed to prepare admin query.");
    }

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

        if (!$stmt) {
            throw new Exception("Failed to prepare customer query.");
        }

        $stmt->bind_param("s", $login_input);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $type = "customer";
    }

    /* ================= ACCOUNT NOT FOUND ================= */

    if (!$user) {
        $_SESSION["login_attempts"][] = $now;

        setValidation("error", LOGIN_FAIL_MSG);
        header("Location: /login.php", true, 303);
        exit();
    }

    /* ================= PASSWORD CHECK ================= */

    if (empty($user["password_hash"]) || !password_verify($password, $user["password_hash"])) {
        $_SESSION["login_attempts"][] = $now;

        setValidation("error", LOGIN_FAIL_MSG);
        header("Location: /login.php", true, 303);
        exit();
    }

    // Successful login: clear the attempt counter for this session.
    unset($_SESSION["login_attempts"]);

    /* ================= REHASH IF NEEDED ================= */
    if (password_needs_rehash($user["password_hash"], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);

        $idColumn = $type === "admin" ? "admin_id" : "customer_id";
        $table = $type === "admin" ? "adminusers" : "customers";
        $idValue = $type === "admin" ? $user["admin_id"] : $user["customer_id"];

        $rehashStmt = $conn->prepare("UPDATE {$table} SET password_hash = ? WHERE {$idColumn} = ?");
        if ($rehashStmt) {
            $rehashStmt->bind_param("si", $newHash, $idValue);
            $rehashStmt->execute();
        }
    }

    /* ================= STATUS CHECK ================= */

    if ((int)$user["status_id"] !== 1) {
        setValidation("error", "Account is deactivated.");
        header("Location: /login.php", true, 303);
        exit();
    }

    /* ================= JWT PAYLOAD ================= */

    $payload = [
        "user_id" => (int)($user["admin_id"] ?? $user["customer_id"]),
        "username" => $user["username"]
            ?? $user["admin_email"]
            ?? $user["email"],
        "role" => $type,
        "role_id" => $user["role_id"] ?? null
    ];

    /* ================= SESSION REGENERATION ================= */
    session_regenerate_id(true);

    /* ================= GENERATE JWT ================= */

    $jwt = JwtHelper::generateToken($payload);

    /* ================= SET AUTH COOKIE ================= */

    $isHttps = (
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    ) || (
        ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    );

    setcookie("auth_token", $jwt, [
        "expires" => time() + 3600,
        "path" => "/",
        "httponly" => true,
        "secure" => $isHttps,
        "samesite" => "Lax"
    ]);

    /* ================= STORE BASIC SESSION DATA ================= */

    $_SESSION["user_id"] = $payload["user_id"];
    $_SESSION["role"] = $payload["role"];

    // admin_only.php (and possibly other protected pages) checks these
    // specific keys, so set them too for admin logins.
    if ($type === "admin") {
        $_SESSION["admin_id"] = $payload["user_id"];
        $_SESSION["role_id"] = $payload["role_id"];
    }

    /* ================= REDIRECT ================= */

    if ($type === "admin") {
        header("Location: /dashboard.php", true, 303);
    } else {
        header("Location: /customerside/homepage.php", true, 303);
    }

    exit();

} catch (Exception $e) {

    error_log("Login Error: " . $e->getMessage());

    setValidation(
        "error",
        "A system error occurred. Please try again."
    );

    header("Location: /login.php", true, 303);
    exit();
}