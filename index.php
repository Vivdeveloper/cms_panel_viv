<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cms_core.php';

/*
 * If this script handles a /page-slug URL (e.g. php -S localhost:9000 index.php routes all requests here),
 * render the inner page instead of the homepage. Normal Apache + .htaccess sends /slug to view.php only.
 */
if (cms_request_inner_page_slug_from_path() !== '') {
    require __DIR__ . '/view.php';
    exit;
}

if (cms_public_should_show_maintenance()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="' . cms_escape(cms_default_lang()) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Maintenance — ' . cms_escape(cms_brand()) . '</title><link rel="stylesheet" href="' . cms_escape(cms_url('public_style.css')) . '"></head><body style="background:#eef2f7;color:#0f172a;font-family:system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;"><p style="color:#64748b;">We are updating the site. Please check back shortly.</p></body></html>';
    exit;
}

$pages = getAllCMSPages();
$homePage = cms_resolve_home_page_for_index($pages);

$brand = cms_brand();
$homeTitle = $homePage ? ($homePage['title'] ?? 'Home') : 'Home';
$homeDesc  = $homePage ? trim((string) ($homePage['meta_description'] ?? '')) : '';
$homeOg    = $homePage ? trim((string) ($homePage['og_image'] ?? '')) : '';
$canonical = cms_home_url();
$homeTpl   = $homePage ? cms_normalize_page_template($homePage['page_template'] ?? 'default') : 'default';
$homeBodyTpl = cms_page_template_body_classes($homeTpl);
?>
<!DOCTYPE html>
<html lang="<?php echo cms_escape(cms_default_lang()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo cms_escape($homeTitle . ' — ' . $brand); ?></title>
    <?php
    cms_render_seo_head([
        'title'       => $homeTitle,
        'description' => $homeDesc !== '' ? $homeDesc : cms_site_tagline(),
        'canonical'   => $canonical,
        'og_image'    => $homeOg,
        'brand'       => $brand,
        'lang'        => cms_default_lang(),
    ]);
    ?>
    <link rel="stylesheet" href="<?php echo cms_escape(cms_url('public_style.css')); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        .dynamic-container { margin-top: <?php echo ($homePage && $homeTpl === 'canvas') ? '0' : '150px'; ?>; padding: <?php echo ($homePage && $homeTpl === 'canvas') ? '24px 20px 80px' : '20px'; ?>; }
        .cms-draft-banner {
            position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
            background: #b45309; color: #fff; text-align: center; padding: 8px 8px;
            font-size: 13px; font-weight: 600;
        }
    </style>
</head>
<body class="<?php echo cms_escape($homeBodyTpl); ?>">
    <?php cms_echo_site_html_snippet('inject_body_open_html'); ?>
    <?php if ($homePage && ($homePage['status'] ?? 'draft') !== 'published' && cms_is_admin_preview()): ?>
    <div class="cms-draft-banner" role="status">Home page is a draft — public visitors still see the placeholder below until you publish.</div>
    <?php endif; ?>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php if (!$homePage || $homeTpl !== 'canvas'): ?>
    <?php getHeader('Home'); ?>
    <?php endif; ?>

    <?php
    if ($homePage): ?>
        <style><?php echo $homePage['css']; ?></style>
        <main class="dynamic-container section">
            <?php echo cms_contact_flash_message_html(); ?>
            <?php echo cms_apply_page_shortcodes($homePage['html'], cms_home_url()); ?>
        </main>
    <?php else: ?>
        <main class="section" style="text-align:center; padding-top:100px;">
            <div style="background:#fff; border:1px dashed rgba(15,23,42,0.15); padding: 50px; border-radius: 20px; max-width: 600px; margin: 0 auto; box-shadow:0 8px 30px rgba(15,23,42,0.06);">
                <h2 style="color: #4facfe; margin-bottom: 15px;">Default design page not found</h2>
                <p style="color: #475569; font-size: 16px;">Please select your dynamic design from the Admin Panel and mark it as 'Home'.</p>
                <div style="margin-top:25px;">
                    <a href="admin.php" style="background: #4facfe; color: black; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size:14px;">Open Admin Panel</a>
                </div>
            </div>
        </main>
    <?php endif; ?>

    <?php if (!$homePage || $homeTpl !== 'canvas'): ?>
    <?php cms_echo_site_html_snippet('inject_footer_html'); ?>
    <?php endif; ?>
    <script src="main.js"></script>
</body>
</html>
