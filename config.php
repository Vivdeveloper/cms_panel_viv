<?php
// PHP Shared Header & Logic

if (!defined('CMS_DATA_DIR')) {
    define('CMS_DATA_DIR', __DIR__ . '/pages_data/');
}

/**
 * WordPress-style site URL detection.
 * Builds full absolute URL: https://domain.com/subfolder/
 */
function cms_site_url() {
    static $url = null;
    if ($url !== null) {
        return $url;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '.' || $dir === '') {
        $dir = '';
    }

    $url = $scheme . '://' . $host . $dir . '/';
    return $url;
}

function cms_url($file = '') {
    return cms_site_url() . ltrim($file, '/');
}

/** Public home URL (works without mod_rewrite). */
function cms_home_url() {
    return cms_url('index.php');
}

/** Clean public URL for an inner page (requires .htaccess rewrites on Apache). */
function cms_page_url($slug) {
    $slug = (string) $slug;
    if ($slug === '') {
        return cms_home_url();
    }
    return rtrim(cms_site_url(), '/') . '/' . rawurlencode($slug);
}

function cms_invalidate_site_settings() {
    $GLOBALS['_cms_site_settings_dirty'] = true;
}

function getSiteSettings() {
    static $cache = null;
    if (!empty($GLOBALS['_cms_site_settings_dirty'])) {
        $cache = null;
        $GLOBALS['_cms_site_settings_dirty'] = false;
    }
    if ($cache !== null) {
        return $cache;
    }
    $defaults = [
        'brand'               => 'creativ3.co',
        'phone'               => '9987842957',
        'whatsapp'            => '9987842957',
        'repo'                => 'Vivdeveloper/cms_panel_viv',
        'default_lang'        => 'en',
        'site_tagline'        => '',
        'default_og_image'    => '',
        'robots_extra'        => '',
        'analytics_head_html' => '',
        'maintenance_mode'    => false,
    ];
    $path = CMS_DATA_DIR . 'site_settings.json';
    if (!is_file($path)) {
        $cache = $defaults;
        return $cache;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        $cache = $defaults;
        return $cache;
    }
    $cache = array_merge($defaults, $decoded);
    if (isset($cache['maintenance_mode'])) {
        $cache['maintenance_mode'] = (bool) $cache['maintenance_mode'];
    }
    return $cache;
}

function cms_brand() {
    return (string) (getSiteSettings()['brand'] ?? 'creativ3.co');
}

function cms_phone() {
    return (string) (getSiteSettings()['phone'] ?? '');
}

function cms_whatsapp() {
    return (string) (getSiteSettings()['whatsapp'] ?? '');
}

function cms_repo() {
    return (string) (getSiteSettings()['repo'] ?? '');
}

function cms_default_lang() {
    return (string) (getSiteSettings()['default_lang'] ?? 'en');
}

function cms_site_tagline() {
    return (string) (getSiteSettings()['site_tagline'] ?? '');
}

function cms_default_og_image() {
    return trim((string) (getSiteSettings()['default_og_image'] ?? ''));
}

/**
 * Save whitelisted site settings (caller must enforce auth).
 */
function cms_save_site_settings(array $input) {
    $allowed = [
        'brand', 'phone', 'whatsapp', 'repo', 'default_lang', 'site_tagline',
        'default_og_image', 'robots_extra', 'analytics_head_html', 'maintenance_mode',
    ];
    $current = getSiteSettings();
    $out = [];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $input)) {
            $out[$key] = $current[$key] ?? '';
            continue;
        }
        if ($key === 'maintenance_mode') {
            $out[$key] = isset($input[$key]) && ($input[$key] === '1' || $input[$key] === 1 || $input[$key] === true);
        } else {
            $out[$key] = is_string($input[$key]) ? $input[$key] : (string) $input[$key];
        }
    }
    if (!is_dir(CMS_DATA_DIR)) {
        mkdir(CMS_DATA_DIR, 0755, true);
    }
    file_put_contents(CMS_DATA_DIR . 'site_settings.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    cms_invalidate_site_settings();
}

function cms_is_maintenance_mode() {
    return !empty(getSiteSettings()['maintenance_mode']);
}

function cms_public_should_show_maintenance() {
    if (!cms_is_maintenance_mode()) {
        return false;
    }
    if (!empty($_SESSION['is_admin'])) {
        return false;
    }
    return true;
}

function cms_escape($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Send 404 and render a minimal branded page (no full nav dependency).
 */
function cms_send_404($message = 'Page not found') {
    http_response_code(404);
    $brand = cms_brand();
    $home  = cms_home_url();
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="<?php echo cms_escape(cms_default_lang()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — <?php echo cms_escape($brand); ?></title>
    <link rel="stylesheet" href="<?php echo cms_escape(cms_url('public_style.css')); ?>">
</head>
<body style="background:#050505;color:#fff;font-family:system-ui,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;margin:0;">
    <main style="text-align:center;padding:2rem;max-width:28rem;">
        <h1 style="font-size:1.5rem;margin:0 0 0.75rem;color:#4facfe;">404</h1>
        <p style="color:#aaa;margin:0 0 1.5rem;line-height:1.5;"><?php echo cms_escape($message); ?></p>
        <a href="<?php echo cms_escape($home); ?>" style="display:inline-block;background:#4facfe;color:#050505;padding:10px 22px;border-radius:8px;text-decoration:none;font-weight:700;">Back to home</a>
    </main>
</body>
</html>
    <?php
}

function cms_render_seo_head(array $opts) {
    $title       = $opts['title'] ?? '';
    $description = trim((string) ($opts['description'] ?? ''));
    $canonical   = $opts['canonical'] ?? '';
    $ogImage     = trim((string) ($opts['og_image'] ?? ''));
    $brand       = $opts['brand'] ?? cms_brand();
    $lang        = $opts['lang'] ?? cms_default_lang();

    if ($ogImage === '') {
        $ogImage = cms_default_og_image();
    }

    $fullTitle = $title !== '' ? ($title . ' — ' . $brand) : $brand;
    ?>
    <meta name="description" content="<?php echo cms_escape($description !== '' ? $description : $brand); ?>">
    <link rel="canonical" href="<?php echo cms_escape($canonical); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo cms_escape($title !== '' ? $title : $brand); ?>">
    <meta property="og:description" content="<?php echo cms_escape($description !== '' ? $description : $brand); ?>">
    <meta property="og:url" content="<?php echo cms_escape($canonical); ?>">
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?php echo cms_escape($ogImage); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?php echo $ogImage !== '' ? 'summary_large_image' : 'summary'; ?>">
    <meta name="twitter:title" content="<?php echo cms_escape($title !== '' ? $title : $brand); ?>">
    <meta name="twitter:description" content="<?php echo cms_escape($description !== '' ? $description : $brand); ?>">
    <?php if ($ogImage !== ''): ?>
    <meta name="twitter:image" content="<?php echo cms_escape($ogImage); ?>">
    <?php endif; ?>
    <?php
    $siteUrl = rtrim(cms_site_url(), '/');
    $siteId  = $siteUrl . '/#website';
    $jsonLd  = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type' => 'WebSite',
                '@id'   => $siteId,
                'name'  => $brand,
                'url'   => $siteUrl,
            ],
            [
                '@type'       => 'WebPage',
                'name'        => $title !== '' ? $title : $brand,
                'description' => $description !== '' ? $description : $brand,
                'url'         => $canonical,
                'isPartOf'    => ['@id' => $siteId],
            ],
        ],
    ];
    ?>
    <script type="application/ld+json"><?php echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php
    $analytics = trim((string) (getSiteSettings()['analytics_head_html'] ?? ''));
    if ($analytics !== '') {
        echo $analytics . "\n";
    }
}

function getHeader($title) {
    $brand    = cms_brand();
    $phone    = cms_phone();
    $whatsapp = cms_whatsapp();
    ?>
    <nav class="glass-nav">
        <a href="<?php echo cms_escape(cms_home_url()); ?>" class="logo"><?php echo cms_escape($brand); ?></a>
        <div class="nav-links">
            <a href="<?php echo cms_escape(cms_home_url()); ?>">Home</a>
            <?php
            include_once 'cms_core.php';
            $allPages = getAllCMSPages();
            foreach ($allPages as $p):
                if ($p['is_home'] ?? false) {
                    continue;
                }
                if (($p['status'] ?? 'draft') !== 'published') {
                    continue;
                }
                ?>
                <a href="<?php echo cms_escape(cms_page_url($p['slug'])); ?>"><?php echo cms_escape(ucwords(str_replace('-', ' ', $p['slug']))); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="contact-btn">
            <span class="call-btn">Call: <?php echo cms_escape($phone); ?></span>
            <a href="https://wa.me/<?php echo cms_escape(preg_replace('/\D+/', '', $whatsapp)); ?>" class="whatsapp-btn">WhatsApp</a>
        </div>
    </nav>
    <?php
}

function getPanel() {
    $repo = cms_repo();
    ?>
    <div id="control-panel">
        <button onclick="togglePanel(false)" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: #555; cursor: pointer; font-size: 24px;">&times;</button>
        <h2 style="margin-bottom: 20px; font-size: 24px; font-weight: 700; background: linear-gradient(45deg, #00f2fe, #4facfe); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Admin Control Panel</h2>
        <div style="margin-bottom: 15px;">
            <label style="display: block; color: #aaa; font-size: 14px;">PHP-Driven GitHub Sync (Flat-File)</label>
            <input type="text" id="repo-url" class="panel-input" value="<?php echo cms_escape($repo); ?>">
        </div>
        <div style="margin-bottom: 20px;">
            <label style="display: block; color: #aaa; font-size: 14px;">Select Dynamic Branch</label>
            <select id="branch-select" class="panel-select">
                <option value="main">main</option>
                <option value="master">master</option>
            </select>
            <div id="branch-status" style="font-size: 11px; color: #4facfe; margin-top: 5px;">GitHub Sync Active</div>
        </div>

        <div style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px;">
            <label style="display: block; color: #aaa; font-size: 14px; margin-bottom:10px;">Dynamic Design Archive</label>
            <div style="max-height: 150px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: 8px; padding: 10px;">
                <?php
                include_once 'cms_core.php';
                $pages = getAllCMSPages();
                foreach ($pages as $p): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.03); padding-bottom:5px;">
                    <span style="font-size:12px; color:#fff;"><?php echo cms_escape($p['slug']); ?></span>
                    <div>
                        <a href="<?php echo cms_escape(cms_page_url($p['slug'])); ?>" style="color:#00f2fe; font-size:11px; text-decoration:none; margin-right:10px;">View</a>
                        <a href="admin.php?edit=<?php echo cms_escape($p['slug']); ?>" style="color:#4facfe; font-size:11px; text-decoration:none;">Edit</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button class="panel-btn" onclick="saveSettings()" style="margin-top:20px;">Apply Dynamic Update</button>
        <div style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px; text-align: center;">
            <a href="admin.php" style="color: #00f2fe; font-size: 14px; text-decoration: none; font-weight: 700;">✨ Professional Admin Dashboard</a>
        </div>
        <div style="margin-top: 5px; text-align: center;">
            <a href="panel.php" style="color: #4facfe; font-size: 13px; text-decoration: none; font-weight: 500;">Open Private Full-Screen Dashboard →</a>
        </div>
        <p style="font-size: 11px; color: #444; margin-top: 15px; text-align: center;">Continuous Deployment: Active</p>
    </div>
<?php
}
?>
