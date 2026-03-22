<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cms_core.php';

if (cms_public_should_show_maintenance()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="' . cms_escape(cms_default_lang()) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Maintenance — ' . cms_escape(cms_brand()) . '</title><link rel="stylesheet" href="' . cms_escape(cms_url('public_style.css')) . '"></head><body style="background:#eef2f7;color:#0f172a;font-family:system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;"><p style="color:#64748b;">We are updating the site. Please check back shortly.</p></body></html>';
    exit;
}

$slug = cms_request_inner_page_slug();
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
$pageTpl   = cms_normalize_page_template($page['page_template'] ?? 'default');
$bodyTpl   = cms_page_template_body_classes($pageTpl);
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
        .dynamic-container { margin-top: <?php echo $pageTpl === 'canvas' ? '0' : '150px'; ?>; padding: <?php echo $pageTpl === 'canvas' ? '24px 20px 80px' : '20px'; ?>; }
        .cms-draft-banner {
            position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
            background: #b45309; color: #fff; text-align: center; padding: 8px 12px;
            font-size: 13px; font-weight: 600;
        }
        <?php echo $page['css']; ?>
    </style>
</head>
<body class="<?php echo cms_escape($bodyTpl); ?>">
    <?php cms_echo_site_html_snippet('inject_body_open_html'); ?>
    <?php if (!$published && cms_is_admin_preview()): ?>
    <div class="cms-draft-banner" role="status">Draft preview — not visible to the public. <a href="admin.php?edit=<?php echo cms_escape($slug); ?>" style="color:#fff;margin-left:8px;">Edit</a></div>
    <?php endif; ?>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php if ($pageTpl !== 'canvas'): ?>
    <?php getHeader($title); ?>
    <?php endif; ?>

    <main class="dynamic-container section">
        <?php echo cms_contact_flash_message_html(); ?>
        <?php echo cms_apply_page_shortcodes($page['html'], cms_page_url($slug)); ?>
    </main>

    <?php if ($pageTpl !== 'canvas'): ?>
    <?php cms_echo_site_html_snippet('inject_footer_html'); ?>
    <?php endif; ?>
    <script src="main.js"></script>
</body>
</html>
