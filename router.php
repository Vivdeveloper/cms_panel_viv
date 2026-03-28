<?php
/**
 * Front controller for PHP’s built-in server (same routes as root .htaccess).
 *
 *   php -S localhost:9000 router.php
 *
 * Inner pages load by slug path: /bosch-service-center-mumbai → view.php
 */
declare(strict_types=1);

require_once __DIR__ . '/cms_routing.php';

$raw = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($raw, PHP_URL_PATH);
$uri = is_string($uri) ? str_replace('\\', '/', rawurldecode($uri)) : '/';

$docroot = __DIR__;

// 1. If it's a real file that exists in docroot, serve it normally
if ($uri !== '/' && $uri !== '' && is_file($docroot . $uri)) {
    return false;
}

if (preg_match('#^/(pages_data|users_data)(/|$)#i', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Forbidden';
    return true;
}

if ($uri === '/robots.txt') {
    require $docroot . '/robots.php';
    return true;
}
if ($uri === '/sitemap.xml') {
    require $docroot . '/sitemap.php';
    return true;
}

$path = $docroot . $uri;
if ($uri !== '/' && $uri !== '' && is_dir($path)) {
    return false;
}

// Smart Media Routing: If file not in root, check in uploads/
$mediaExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'mp3', 'zip'];
$uriExt = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
if (in_array($uriExt, $mediaExts, true)) {
    $uploadPath = $docroot . '/uploads' . $uri;
    if (is_file($uploadPath)) {
        $mime = mime_content_type($uploadPath) ?: 'application/octet-stream';
        header("Content-Type: $mime");
        readfile($uploadPath);
        return true;
    }
}

if ($uri === '/' || $uri === '') {
    require $docroot . '/index.php';
    return true;
}

if (preg_match(CMS_INNER_PAGE_URL_REGEX, $uri, $m)) {
    $_GET['page'] = $m[1];
    require $docroot . '/view.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo '404 Not Found';
return true;
