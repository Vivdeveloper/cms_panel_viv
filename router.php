<?php
/**
 * Optional router for PHP’s built-in server so the public URL matches Apache clean URLs:
 *
 *   php -S localhost:9000 router.php
 *
 * Redirects …/index.php → …/ and leaves other requests to the default static handler
 * (and existing index.php DirectoryIndex for /).
 */
if (PHP_SAPI !== 'cli-server') {
    return false;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) && $path !== '' ? $path : '/';
if (substr($path, -10) !== '/index.php' && $path !== '/index.php') {
    return false;
}

$dir = str_replace('\\', '/', dirname($path));
if ($dir === '/' || $dir === '.' || $dir === '') {
    $loc = '/';
} else {
    $loc = rtrim($dir, '/') . '/';
}
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
header('Location: ' . $loc . $qs, true, 301);
exit;
