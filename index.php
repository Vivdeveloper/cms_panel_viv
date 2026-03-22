<?php
include 'config.php';
include 'cms_core.php';

if (cms_public_should_show_maintenance()) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="' . cms_escape(cms_default_lang()) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Maintenance — ' . cms_escape(cms_brand()) . '</title><link rel="stylesheet" href="' . cms_escape(cms_url('public_style.css')) . '"></head><body style="background:#050505;color:#fff;font-family:system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;"><p style="color:#888;">We are updating the site. Please check back shortly.</p></body></html>';
    exit;
}

$pages = getAllCMSPages();
$homePage = null;
foreach ($pages as $p) {
    if (!($p['is_home'] ?? false)) {
        continue;
    }
    $pub = (($p['status'] ?? 'draft') === 'published');
    if ($pub || cms_is_admin_preview()) {
        $homePage = $p;
        break;
    }
}

$brand = cms_brand();
$homeTitle = $homePage ? ($homePage['title'] ?? 'Home') : 'Home';
$homeDesc  = $homePage ? trim((string) ($homePage['meta_description'] ?? '')) : '';
$homeOg    = $homePage ? trim((string) ($homePage['og_image'] ?? '')) : '';
$canonical = cms_home_url();
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
        .cms-draft-banner {
            position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
            background: #b45309; color: #fff; text-align: center; padding: 8px 12px;
            font-size: 13px; font-weight: 600;
        }
    </style>
</head>
<body>
    <?php if ($homePage && ($homePage['status'] ?? 'draft') !== 'published' && cms_is_admin_preview()): ?>
    <div class="cms-draft-banner" role="status">Home page is a draft — public visitors still see the placeholder below until you publish.</div>
    <?php endif; ?>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php getHeader('Home'); ?>

    <?php
    if ($homePage): ?>
        <style><?php echo $homePage['css']; ?></style>
        <main class="dynamic-container section">
            <?php echo $homePage['html']; ?>
        </main>
    <?php else: ?>
        <main class="section" style="text-align:center; padding-top:100px;">
            <div style="background:rgba(255,255,255,0.03); border:1px dashed rgba(255,255,255,0.1); padding: 50px; border-radius: 20px; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #4facfe; margin-bottom: 15px;">Dafult design page not found</h2>
                <p style="color: #666; font-size: 16px;">Please select your dynamic design from the Admin Panel and mark it as 'Home'.</p>
                <div style="margin-top:25px;">
                    <a href="admin.php" style="background: #4facfe; color: black; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size:14px;">Open Admin Panel</a>
                </div>
            </div>
        </main>
    <?php endif; ?>

    <script src="main.js"></script>
</body>
</html>
