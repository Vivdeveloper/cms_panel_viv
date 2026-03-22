<?php
/**
 * Router for PHP’s built-in server (Apache .htaccess is ignored there).
 *
 *   php -S localhost:9000 router.php
 *
 * Maps /your-page-slug → view.php?page=your-page-slug, serves static assets, runs PHP scripts.
 */
if (PHP_SAPI !== 'cli-server') {
    return false;
}

$uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? str_replace('\\', '/', $path) : '/';

// Canonical: /index.php → /
if (substr($path, -10) === '/index.php' || $path === '/index.php') {
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
}

$docRoot = __DIR__;
$full    = realpath($docRoot . $path);

// Existing file (css, js, images, php entrypoints) — let built-in server handle non-router targets
if ($path !== '/' && $full !== false && is_file($full)) {
    if (!preg_match('/\.php$/i', $full)) {
        return false;
    }
    if (strcasecmp(basename($full), 'router.php') === 0) {
        return false;
    }
    return false;
}

$trim = trim($path, '/');

if ($trim === '' || strcasecmp($trim, 'index.php') === 0) {
    require $docRoot . '/index.php';
    return true;
}

// Direct PHP script request without matching file above (e.g. typo) — fall through
if (preg_match('/\.php$/i', $trim)) {
    return false;
}

// Clean URL → inner page (same pattern as .htaccess)
if (preg_match('#^[a-z0-9][a-z0-9\-]*$#', $trim)) {
    $_GET['page']    = $trim;
    $_REQUEST['page'] = $trim;
    require $docRoot . '/view.php';
    return true;
}

return false;
