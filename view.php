<?php
include 'config.php';
include 'cms_core.php';

if (cms_public_should_show_maintenance()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="' . cms_escape(cms_default_lang()) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Maintenance — ' . cms_escape(cms_brand()) . '</title><link rel="stylesheet" href="' . cms_escape(cms_url('public_style.css')) . '"></head><body style="background:#050505;color:#fff;font-family:system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;"><p style="color:#888;">We are updating the site. Please check back shortly.</p></body></html>';
    exit;
}

$slug = isset($_GET['page']) ? cms_sanitize_slug($_GET['page']) : '';
if ($slug === '') {
    cms_send_404('Missing page.');
    exit;
}

$dataFile = __DIR__ . '/pages_data/' . $slug . '.json';

if (!file_exists($dataFile)) {
    cms_send_404('This page does not exist.');
    exit;
}

$page = json_decode(file_get_contents($dataFile), true);
$published = (($page['status'] ?? 'draft') === 'published');
if (!$published && !cms_is_admin_preview()) {
    cms_send_404('This page is not published yet.');
    exit;
}

$brand = cms_brand();
$title = $page['title'] ?? ucwords(str_replace('-', ' ', $slug));
$desc  = trim((string) ($page['meta_description'] ?? ''));
$og    = trim((string) ($page['og_image'] ?? ''));
$canonical = cms_page_url($slug);
?>
<!DOCTYPE html>
<html lang="<?php echo cms_escape(cms_default_lang()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo cms_escape($title . ' — ' . $brand); ?></title>
    <?php
    cms_render_seo_head([
        'title'       => $title,
        'description' => $desc,
        'canonical'   => $canonical,
        'og_image'    => $og,
        'brand'       => $brand,
        'lang'        => cms_default_lang(),
    ]);
    ?>
    <link rel="stylesheet" href="<?php echo cms_escape(cms_url('public_style.css')); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        body { background: #050505; color: #fff; }
        .dynamic-container { margin-top: 150px; padding: 20px; }
        .cms-draft-banner {
            position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
            background: #b45309; color: #fff; text-align: center; padding: 8px 12px;
            font-size: 13px; font-weight: 600;
        }
        <?php echo $page['css']; ?>
    </style>
</head>
<body>
    <?php if (!$published && cms_is_admin_preview()): ?>
    <div class="cms-draft-banner" role="status">Draft preview — not visible to the public. <a href="admin.php?edit=<?php echo cms_escape($slug); ?>" style="color:#fff;margin-left:8px;">Edit</a></div>
    <?php endif; ?>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php getHeader($title); ?>

    <main class="dynamic-container section">
        <?php echo $page['html']; ?>
    </main>

    <script src="main.js"></script>
</body>
</html>
