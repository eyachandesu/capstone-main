<?php

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
    $type = $_COOKIE["validation_type"] ?? "info";

    setcookie("validation_message", "", time() - 3600, "/");
    setcookie("validation_type", "", time() - 3600, "/");

    $validation_scheme = match ($type) {
        "success" => [
            "bg" => "bg-[#E3FCE4]",
            "message_title" => "text-[#1E5306]",
            "message_subtext" => "text-[#33AD3D]",
            "svg_icon" => "✓"
        ],
        "error" => [
            "bg" => "bg-[#FCE3E3]",
            "message_title" => "text-[#530606]",
            "message_subtext" => "text-[#AD3333]",
            "svg_icon" => "✕"
        ],
        default => [
            "bg" => "bg-[#FDF5CA]",
            "message_title" => "text-[#AA4C08]",
            "message_subtext" => "text-[#AD7A33]",
            "svg_icon" => "!"
        ]
    };

    return <<<HTML
    <div class="{$validation_scheme['bg']} px-4 py-2 rounded">
        <div class="flex gap-1 items-center">
            <span>{$validation_scheme['svg_icon']}</span>
            <p class="{$validation_scheme['message_title']} font-medium">Notification</p>
        </div>
        <p class="{$validation_scheme['message_subtext']} font-light ml-7">{$message}</p>
    </div>
HTML;
}