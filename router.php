<?php
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path === '/') {
    $file = __DIR__ . '/index.html';
}

if (is_file($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, ['html', 'htm'])) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Type: text/html; charset=UTF-8');
        readfile($file);
        return true;
    }
    $imgMimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    if (isset($imgMimes[$ext]) && strpos($path, '/uploads/') === 0) {
        header('Content-Type: ' . $imgMimes[$ext]);
        header('Cache-Control: public, max-age=3600');
        readfile($file);
        return true;
    }
    return false;
}

return false;
