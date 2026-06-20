<?php
if (ob_get_length())
    ob_clean();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Config and autoload
require_once __DIR__ . "/config/config.php";
require_once __DIR__ . "/vendor/autoload.php";

//middleware
require_once __DIR__ . "/middleware/auth_middleware.php";

// Helpers
require_once __DIR__ . "/helper/generalValidationMessage.php";
require_once __DIR__ . '/helper/jwt_helper.php';
require_once __DIR__ . '/helper/toast.php';



// Functions
?>