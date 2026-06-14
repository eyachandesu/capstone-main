<?php
// Always start output buffering so headers (setcookie) can still be sent
if (ob_get_level() === 0) {
    ob_start();
}

function setValidation(string $cookieType, string $message): void
{
    $expireTime = time() + 60;

    setcookie("validation_message", $message, $expireTime, "/");
    setcookie("validation_type", $cookieType, $expireTime, "/");
}

function showValidation(): ?string
{
    if (!isset($_COOKIE["validation_message"])) {
        return null;
    }

    $message = htmlspecialchars($_COOKIE["validation_message"]);
    $type = $_COOKIE["validation_type"];

    // ✅ clear cookies here (safe because of ob_start at top)
    setcookie("validation_message", "", time() - 3600, "/");
    setcookie("validation_type", "", time() - 3600, "/");

    $validation_scheme = match ($type) {
        "success" => [
            "bg" => "bg-[#E3FCE4]",
            "message_title" => "text-[#1E5306]",
            "message_subtext" => "text-[#33AD3D]",
            "svg_icon" => <<<SVG
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 text-[#09E05F]">
      <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
    </svg>
SVG
        ],
        "error" => [
            "bg" => "bg-[#FCE3E3]",
            "message_title" => "text-[#530606]",
            "message_subtext" => "text-[#AD3333]",
            "svg_icon" => <<<SVG
   <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 text-[#E00909]">
     <path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" />
   </svg>
SVG
        ],
        "info" => [
            "bg" => "bg-[#FDF5CA]",
            "message_title" => "text-[#AA4C08]",
            "message_subtext" => "text-[#AD7A33]",
            "svg_icon" => <<<SVG
   <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6 text-[#FF9C23]">
     <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
   </svg>
SVG
        ],
        default => [
            "bg" => "bg-gray-700",
            "message_title" => "text-white",
            "message_subtext" => "text-gray-200",
            "svg_icon" => "",
        ],
    };

    return <<<HTML
    <div class="{$validation_scheme['bg']} px-4 py-2 rounded">
        <div class="flex gap-1 items-center">
            {$validation_scheme['svg_icon']}
            <p class="{$validation_scheme['message_title']} font-medium">Notification</p>
        </div>
        <p class="{$validation_scheme['message_subtext']} font-light ml-7">{$message}</p>
    </div>
HTML;
}

// Flush buffer at end of script so headers + HTML go together
register_shutdown_function(function () {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
});
