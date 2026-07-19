<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helper/jwt_helper.php';
require_once __DIR__ . '/../helper/validation.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /login.php", true, 303);
    exit();
}

/* ==========================
   CSRF CHECK
========================== */

if (
    empty($_POST["csrf_token"]) ||
    empty($_SESSION["csrf_token"]) ||
    !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
) {
    setValidation("error", "Invalid form submission.");
    header("Location: /login.php", true, 303);
    exit();
}

/* ==========================
   RATE LIMIT
========================== */

$_SESSION["login_attempts"] = $_SESSION["login_attempts"] ?? [];

$now = time();

$_SESSION["login_attempts"] = array_filter(
    $_SESSION["login_attempts"],
    fn($t) => $t > ($now - 60)
);

if (count($_SESSION["login_attempts"]) >= 5) {

    setValidation("error", "Too many login attempts. Please wait 1 minute.");

    header("Location: /login.php", true, 303);
    exit();
}

/* ==========================
   INPUT
========================== */

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($username === "" || $password === "") {

    setValidation("error", "Username and Password are required.");

    header("Location: /login.php", true, 303);
    exit();
}

define("LOGIN_FAIL", "Invalid username or password.");

try {

    /* ==========================
       ADMIN LOGIN
    ========================== */

    $stmt = $conn->prepare("
        SELECT
            admin_id,
            username,
            password_hash,
            role_id,
            status_id
        FROM adminusers
        WHERE username = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("s", $username);

    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    $type = "admin";

    /* ==========================
       CUSTOMER LOGIN
    ========================== */

    if (!$user) {

        $stmt = $conn->prepare("
            SELECT
                customer_id,
                username,
                password_hash,
                status_id
            FROM customers
            WHERE username = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("s", $username);

        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        $type = "customer";
    }

    /* ==========================
       USER NOT FOUND
    ========================== */

    if (!$user) {

        $_SESSION["login_attempts"][] = $now;

        setValidation("error", LOGIN_FAIL);

        header("Location: /login.php", true, 303);
        exit();
    }

    /* ==========================
       ACCOUNT STATUS
    ========================== */

    if ((int)$user["status_id"] !== 1) {

        setValidation("error", "Your account has been deactivated.");

        header("Location: /login.php", true, 303);
        exit();
    }

    /* ==========================
       PASSWORD CHECK
    ========================== */

    if (
        empty($user["password_hash"]) ||
        !password_verify($password, $user["password_hash"])
    ) {

        $_SESSION["login_attempts"][] = $now;

        setValidation("error", LOGIN_FAIL);

        header("Location: /login.php", true, 303);
        exit();
    }

    unset($_SESSION["login_attempts"]);

    /* ==========================
       REHASH PASSWORD
    ========================== */

    if (password_needs_rehash($user["password_hash"], PASSWORD_DEFAULT)) {

        $newHash = password_hash($password, PASSWORD_DEFAULT);

        if ($type === "admin") {

            $update = $conn->prepare("
                UPDATE adminusers
                SET password_hash = ?
                WHERE admin_id = ?
            ");

            $update->bind_param(
                "si",
                $newHash,
                $user["admin_id"]
            );

        } else {

            $update = $conn->prepare("
                UPDATE customers
                SET password_hash = ?
                WHERE customer_id = ?
            ");

            $update->bind_param(
                "si",
                $newHash,
                $user["customer_id"]
            );
        }

        $update->execute();
    }

    /* ==========================
       JWT PAYLOAD
    ========================== */

    $payload = [

        "user_id" => $type === "admin"
            ? (int)$user["admin_id"]
            : (int)$user["customer_id"],

        "username" => $user["username"],

        "role" => $type,

        "role_id" => $user["role_id"] ?? null
    ];

    session_regenerate_id(true);

    $jwt = JwtHelper::generateToken($payload);

    $secure = (
        isset($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "off"
    );

    setcookie(
        "auth_token",
        $jwt,
        [
            "expires" => time() + 3600,
            "path" => "/",
            "httponly" => true,
            "secure" => $secure,
            "samesite" => "Lax"
        ]
    );

    /* ==========================
       SESSION
    ========================== */

    $_SESSION["user_id"] = $payload["user_id"];
    $_SESSION["username"] = $payload["username"];
    $_SESSION["role"] = $payload["role"];

    if ($type === "admin") {

        $_SESSION["admin_id"] = $payload["user_id"];
        $_SESSION["role_id"] = $payload["role_id"];

        header("Location: /dashboard.php", true, 303);

    } else {

        $_SESSION["customer_id"] = $payload["user_id"];

        header("Location: /customerside/homepage.php", true, 303);
    }

    exit();

} catch (Throwable $e) {

    error_log($e);

    setValidation(
        "error",
        "A system error occurred."
    );

    header("Location: /login.php", true, 303);
    exit();
}