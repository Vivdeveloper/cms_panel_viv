<?php
/**
 * Download a CMS page as one .html file (admin session required).
 * Page CSS is in <style>; page HTML is in <body>.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cms_core.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: viv-admin.php');
    exit;
}

$slug = isset($_GET['slug']) ? cms_sanitize_slug((string) $_GET['slug']) : '';
if ($slug === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Missing or invalid page slug.';
    exit;
}

$page = getCMSPage($slug);
if (!is_array($page)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Page not found.';
    exit;
}

$title = trim((string) ($page['title'] ?? ''));
if ($title === '') {
    $title = ucwords(str_replace('-', ' ', $slug));
}
$html = (string) ($page['html'] ?? '');
$css  = (string) ($page['css'] ?? '');
$metaDesc = trim((string) ($page['meta_description'] ?? ''));

$base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $slug);
if ($base === '') {
    $base = 'page';
}

$lang = cms_default_lang();

$safeFile = $base . '.html';
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $safeFile) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?php echo cms_escape($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo cms_escape($title); ?></title>
    <?php if ($metaDesc !== ''): ?>
    <meta name="description" content="<?php echo cms_escape($metaDesc); ?>">
    <?php endif; ?>
    <meta name="generator" content="CMS export — HTML and CSS in one file">
    <style>
/* Page CSS (from CMS) + base layout */
body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
<?php echo $css; ?>

    </style>
</head>
<body>
<?php echo $html; ?>

</body>
</html>
