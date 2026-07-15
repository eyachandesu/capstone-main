<?php
/**
 * Router for PHP's built-in dev server.
 *
 * Run from the PROJECT ROOT (the folder containing controllers/, config/,
 * helper/, middleware/, public/, etc.) like this:
 *
 *   php -S localhost:8000 router.php
 *
 * Do NOT use -t public with this script.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$mimeTypes = [
    'css'  => 'text/css',
    'js'   => 'application/javascript',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
    'json' => 'application/json',
    'woff' => 'font/woff',
    'woff2'=> 'font/woff2',
];

function serveStatic(string $file, array $mimeTypes): void
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($file);
}

// Folders outside public/ that are allowed to be reached directly via URL.
// - /controllers/ handles form submissions (login_handler.php etc.) - PHP files.
// - /uploads/ holds product images etc. - static files, served as-is.
$allowedOutsidePublicPhp = ['/controllers/'];
$allowedOutsidePublicStatic = ['/uploads/'];

foreach ($allowedOutsidePublicPhp as $prefix) {
    if (str_starts_with($uri, $prefix)) {
        $file = __DIR__ . $uri;
        if (is_file($file)) {
            require $file;
            return true;
        }
        http_response_code(404);
        echo "Not found: $uri";
        return true;
    }
}

foreach ($allowedOutsidePublicStatic as $prefix) {
    if (str_starts_with($uri, $prefix)) {
        $file = __DIR__ . $uri;
        if (is_file($file)) {
            serveStatic($file, $mimeTypes);
            return true;
        }
        http_response_code(404);
        echo "Not found: $uri";
        return true;
    }
}

// Everything else is resolved against public/, since that's the doc root.
$publicFile = __DIR__ . '/public' . ($uri === '/' ? '/index.php' : $uri);

if (!is_file($publicFile)) {
    http_response_code(404);
    echo "Not found: $uri";
    return true;
}

$ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));

if ($ext === 'php') {
    // Run PHP files ourselves so paths resolve against public/ correctly.
    chdir(dirname($publicFile));
    require $publicFile;
    return true;
}

// Serve static assets (css, js, images, etc.) with a correct MIME type.
serveStatic($publicFile, $mimeTypes);
return true;