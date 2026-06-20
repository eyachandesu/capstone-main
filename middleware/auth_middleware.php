<?php
require_once __DIR__ . '/../helper/jwt_helper.php';
require_once __DIR__ . '/../helper/generalValidationMessage.php';

function checkAuth($requiredRole = null)
{
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

    // 1. No token → force login
    if (empty($_COOKIE['auth_token'])) {
        unset($_SESSION['user_id']);
        header("Location: /public/login.php");
        exit();
    }

    // 2. Validate token
    $decoded = JwtHelper::verifyToken($_COOKIE['auth_token']);

    if (!$decoded || !isset($decoded->data)) {

        // FULL cookie cleanup (IMPORTANT FIX)
        setcookie("auth_token", "", [
            "expires" => time() - 3600,
            "path" => "/",
            "domain" => "",
            "secure" => $isSecure,
            "httponly" => true,
            "samesite" => "Lax"
        ]);

        unset($_SESSION['user_id']);

        setValidation("info", "Session expired. Please login again.");
        header("Location: /public/login.php");
        exit();
    }

    $userData = $decoded->data;

    // 3. Role check
    if ($requiredRole) {
        $userRole = $userData->role ?? null;

        // Convert a single string to an array so we can use in_array() for everything
        $allowedRoles = is_array($requiredRole) ? $requiredRole : [$requiredRole];

        if (!in_array($userRole, $allowedRoles)) {
            setValidation("error", "Access denied: Insufficient permissions.");
            header("Location: /index.php");
            exit();
        }
    }

    // 4. Sync session (safe)
    $_SESSION['user_id'] = $userData->user_id ?? null;
    $_SESSION['role'] = $userData->role ?? null;

    return $userData;
}