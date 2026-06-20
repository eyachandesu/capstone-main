<?php
require_once __DIR__ . "/helper/jwt_helper.php";

$token = $_COOKIE['auth_token'] ?? null;

if (!$token) {
    header("Location: /public/login.php");
    exit();
}

$decoded = JwtHelper::verifyToken($token);

if (!$decoded || !isset($decoded->data)) {
    setcookie("auth_token", "", time() - 3600, "/");
    header("Location: /public/login.php");
    exit();
}

// VALID
header("Location: /public/dashboard.php");
exit();