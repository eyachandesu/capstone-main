<?php
/**
 * Sets a short-lived cookie pair that login.php (and any other page)
 * reads back to display a one-time flash message after a redirect.
 *
 * Usage: setValidation("error", "Invalid username or password.");
 */
function setValidation(string $type, string $message): void
{
    // Expires in 30 seconds - just long enough to survive the redirect
    // and be read once on the next page load.
    $expires = time() + 30;

    setcookie("validation_type", $type, $expires, "/");
    setcookie("validation_message", $message, $expires, "/");
}