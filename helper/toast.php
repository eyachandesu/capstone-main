<?php
// Set the toast cookie
function setToast(string $message, string $type = "success"): void
{
    setcookie("toast_message", $message, time() + 5, "/");
    setcookie("toast_type", $type, time() + 5, "/");
}

// Display the toast
function showToast(): void
{
    if (!isset($_COOKIE["toast_message"])) {
        return;
    }

    $message = $_COOKIE["toast_message"];
    $type = $_COOKIE["toast_type"] ?? "success";

    // Clear the cookies
    setcookie("toast_message", "", time() - 3600, "/");
    setcookie("toast_type", "", time() - 3600, "/");

    $bg = match ($type) {
        "success" => "bg-green-500",
        "error" => "bg-red-500",
        "info" => "bg-blue-500",
        default => "bg-gray-700",
    };

    echo <<<HTML
<div id="toast" class="fixed top-5 right-5 opacity-0 translate-y-[-20px] {$bg} text-white px-6 py-3 rounded shadow-lg z-50 transition-all duration-500 ease-in-out">
    {$message}
</div>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        const toast = document.getElementById('toast');
        if (toast) {
            // Slide down and fade in
            setTimeout(() => {
                toast.classList.remove('opacity-0', 'translate-y-[-20px]');
                toast.classList.add('opacity-100', 'translate-y-0');
            }, 100); // slight delay to trigger animation

            // Hide after 3 seconds
            setTimeout(() => {
                toast.classList.remove('opacity-100', 'translate-y-0');
                toast.classList.add('opacity-0', 'translate-y-[-20px]');
            }, 3000);
        }
    });
</script>
HTML;
}
