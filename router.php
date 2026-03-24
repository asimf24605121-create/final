<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path === '/') {
    $file = __DIR__ . '/index.html';
}

if (is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if ($ext === 'php') {
        return false;
    }

    $mimeTypes = [
        'html' => 'text/html; charset=UTF-8',
        'htm'  => 'text/html; charset=UTF-8',
        'css'  => 'text/css; charset=UTF-8',
        'js'   => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'eot'  => 'application/vnd.ms-fontobject',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
    ];

    if (isset($mimeTypes[$ext])) {
        if (in_array($ext, ['html', 'htm'])) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        } elseif (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'])) {
            header('Cache-Control: public, max-age=3600');
        }
        header('Content-Type: ' . $mimeTypes[$ext]);
        readfile($file);
        return true;
    }

    return false;
}

header('Content-Type: text/html; charset=UTF-8');
http_response_code(404);
echo '<!DOCTYPE html><html><head><title>404 - Not Found</title>';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f0f2f5}';
echo '.box{text-align:center;padding:2rem;background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px}';
echo 'h1{color:#6c63ff;font-size:3rem;margin:0}p{color:#666;margin-top:.5rem}a{color:#6c63ff;text-decoration:none;font-weight:600}</style></head>';
echo '<body><div class="box"><h1>404</h1><p>Page not found</p><a href="/">← Back to Home</a></div></body></html>';
return true;
