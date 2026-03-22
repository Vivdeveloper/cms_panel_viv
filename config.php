<?php
// PHP Shared Header & Logic

if (!defined('CMS_DATA_DIR')) {
    define('CMS_DATA_DIR', __DIR__ . '/pages_data/');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/cms_contact_form.php';

/** Fallback phone / WhatsApp when `site_settings.json` omits them (override in admin or JSON). */
if (!defined('CMS_PUBLIC_PHONE')) {
    define('CMS_PUBLIC_PHONE', '9987842957');
}
if (!defined('CMS_PUBLIC_WHATSAPP')) {
    define('CMS_PUBLIC_WHATSAPP', '+91 9987842957');
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

/** Normalize maintenance flag from JSON or form input (avoids truthy string bugs). */
function cms_normalize_maintenance_bool($raw) {
    if ($raw === true || $raw === 1 || $raw === '1') {
        return true;
    }
    if ($raw === false || $raw === 0 || $raw === '0') {
        return false;
    }
    if ($raw === null || $raw === '') {
        return false;
    }
    if (is_string($raw)) {
        $l = strtolower(trim($raw));
        if (in_array($l, ['true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($l, ['false', 'no', 'off'], true)) {
            return false;
        }
    }
    return (bool) $raw;
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
        'brand'               => 'SEO Website Designer',
        'phone'               => '9987842957',
        'whatsapp'            => '+91 9987842957',
        'default_lang'        => 'en',
        'site_tagline'        => '',
        'default_og_image'    => '',
        'robots_extra'        => '',
        'analytics_head_html' => '',
        'inject_body_open_html' => '',
        'inject_footer_html' => '',
        'maintenance_mode'     => false,
        'sticky_cta_layout'    => 'split',
        'cta_enable_call'      => true,
        'cta_enable_whatsapp'  => true,
        'cta_sticky_desktop'   => false,
        'cta_call_color'       => '#1d4ed8',
        'cta_call_color2'      => '#1e40af',
        'cta_call_label'       => 'Call',
        'header_logo_url'      => '',
        'contact_form_to_email'   => '',
        'contact_form_subject'    => 'New contact from {site}',
        'contact_form_use_custom' => false,
        'contact_form_fields'     => [],
        'smtp_enabled'         => false,
        'smtp_host'            => '',
        'smtp_port'            => 587,
        'smtp_encryption'      => 'tls',
        'smtp_user'            => '',
        'smtp_pass'            => '',
        'smtp_from_email'      => '',
        'smtp_from_name'       => '',
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
    $cache['maintenance_mode'] = cms_normalize_maintenance_bool($cache['maintenance_mode'] ?? false);
    $cache['sticky_cta_layout'] = (($cache['sticky_cta_layout'] ?? 'split') === 'full') ? 'full' : 'split';
    $cache['cta_enable_call']     = !empty($cache['cta_enable_call']);
    $cache['cta_enable_whatsapp'] = !empty($cache['cta_enable_whatsapp']);
    $cache['cta_sticky_desktop']  = !empty($cache['cta_sticky_desktop']);
    $cache['smtp_enabled'] = !empty($cache['smtp_enabled']);
    $enc = strtolower(trim((string) ($cache['smtp_encryption'] ?? 'tls')));
    $cache['smtp_encryption'] = in_array($enc, ['none', 'tls', 'ssl'], true) ? $enc : 'tls';
    $cache['smtp_port'] = (int) ($cache['smtp_port'] ?? 587);
    if ($cache['smtp_port'] <= 0 || $cache['smtp_port'] > 65535) {
        $cache['smtp_port'] = 587;
    }
    $cache['contact_form_use_custom'] = !empty($cache['contact_form_use_custom']);
    $cff = $cache['contact_form_fields'] ?? [];
    $cache['contact_form_fields'] = is_array($cff) ? $cff : [];
    return $cache;
}

function cms_brand() {
    return (string) (getSiteSettings()['brand'] ?? 'creativ3.co');
}

function cms_phone() {
    $s = trim((string) (getSiteSettings()['phone'] ?? ''));
    return $s !== '' ? $s : CMS_PUBLIC_PHONE;
}

function cms_whatsapp() {
    $s = trim((string) (getSiteSettings()['whatsapp'] ?? ''));
    return $s !== '' ? $s : CMS_PUBLIC_WHATSAPP;
}

function cms_phone_tel_digits() {
    $d = preg_replace('/\D+/', '', cms_phone());
    return $d !== '' ? $d : preg_replace('/\D+/', '', CMS_PUBLIC_PHONE);
}

function cms_whatsapp_digits() {
    $d = preg_replace('/\D+/', '', cms_whatsapp());
    return $d !== '' ? $d : preg_replace('/\D+/', '', CMS_PUBLIC_WHATSAPP);
}

/**
 * WhatsApp chat URL with a pre-filled password-reset request (for "Forgot password?" on login).
 * Empty string if no WhatsApp number is configured.
 */
function cms_whatsapp_password_reset_url() {
    $digits = cms_whatsapp_digits();
    if ($digits === '') {
        return '';
    }
    $site = rtrim(cms_site_url(), '/');
    $brand = trim(str_replace(["\r\n", "\r", "\n"], ' ', cms_brand()));
    $lines = [
        'Hello,',
        '',
        'I need help resetting my password for the CMS (' . $brand . ').',
        '',
        'Website: ' . $site,
        'Username: ',
        '',
        'Thank you.',
    ];
    $text = implode("\n", $lines);

    return 'https://wa.me/' . rawurlencode($digits) . '?text=' . rawurlencode($text);
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

/** Sanitize stored header logo path or absolute URL. */
function cms_sanitize_header_logo_url($raw) {
    $s = trim((string) $raw);
    if ($s === '') {
        return '';
    }
    if (preg_match('#^(javascript|data|vbscript):#i', $s)) {
        return '';
    }
    if (preg_match('#^https?://#i', $s)) {
        return $s;
    }
    if (strpos($s, '..') !== false || preg_match('#[\s<>"\'{}|\\^`\[\]]#', $s)) {
        return '';
    }
    if (preg_match('#^[a-zA-Z0-9._/-]+$#', $s)) {
        return $s;
    }
    return '';
}

/**
 * Save an uploaded header logo under uploads/. Caller must verify auth and CSRF.
 *
 * @param array $file Element of $_FILES (e.g. $_FILES['header_logo_file'])
 * @return string Relative path such as uploads/header_logo_20260322_120000_ab12cd34.png, or null on failure.
 */
function cms_handle_header_logo_upload(array $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }
    $maxBytes = 3 * 1024 * 1024;
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        return null;
    }
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $orig = basename((string) ($file['name'] ?? ''));
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        return null;
    }
    $logoMimeMap = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'gif' => ['image/gif'],
        'webp' => ['image/webp'], 'svg' => ['image/svg+xml'],
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($tmp);
    $validMimes = $logoMimeMap[$ext] ?? [];
    if ($detectedMime === false || ($validMimes !== [] && !in_array($detectedMime, $validMimes, true))) {
        return null;
    }
    if ($ext === 'svg') {
        $svgContent = file_get_contents($tmp);
        if (preg_match('/<\s*script|on\w+\s*=|javascript\s*:/i', $svgContent)) {
            return null;
        }
    }
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return null;
    }
    $destName = 'header_logo_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $destName;
    if (!move_uploaded_file($tmp, $destPath)) {
        return null;
    }
    return 'uploads/' . $destName;
}

/** Absolute URL for header logo img src, or empty. */
function cms_header_logo_url_resolved() {
    $u = cms_sanitize_header_logo_url(getSiteSettings()['header_logo_url'] ?? '');
    if ($u === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    return rtrim(cms_site_url(), '/') . '/' . ltrim($u, '/');
}

/**
 * Save whitelisted site settings (caller must enforce auth).
 */
function cms_save_site_settings(array $input) {
    $allowed = [
        'brand', 'default_lang', 'site_tagline',
        'default_og_image', 'robots_extra', 'analytics_head_html',
        'inject_body_open_html', 'inject_footer_html',
        'maintenance_mode',
        'header_logo_url',
    ];
    $current = getSiteSettings();
    $out = [];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $input)) {
            $out[$key] = $current[$key] ?? '';
            continue;
        }
        if ($key === 'maintenance_mode') {
            $v = $input[$key] ?? '0';
            if (is_array($v)) {
                $v = (in_array('1', $v, true) || in_array(1, $v, true) || in_array(true, $v, true)) ? '1' : '0';
            }
            $out[$key] = cms_normalize_maintenance_bool($v);
        } elseif ($key === 'header_logo_url') {
            $out[$key] = cms_sanitize_header_logo_url($input[$key] ?? '');
        } else {
            $out[$key] = is_string($input[$key]) ? $input[$key] : (string) $input[$key];
        }
    }
    $out['phone']              = $current['phone'] ?? CMS_PUBLIC_PHONE;
    $out['whatsapp']           = $current['whatsapp'] ?? CMS_PUBLIC_WHATSAPP;
    $out['sticky_cta_layout']   = $current['sticky_cta_layout'] ?? 'split';
    $out['cta_enable_call']      = $current['cta_enable_call'] ?? true;
    $out['cta_enable_whatsapp']  = $current['cta_enable_whatsapp'] ?? true;
    $out['cta_sticky_desktop']   = $current['cta_sticky_desktop'] ?? false;
    $out['cta_call_color']       = cms_sanitize_hex_color($current['cta_call_color'] ?? '', '#1d4ed8');
    $out['cta_call_color2']      = cms_sanitize_hex_color($current['cta_call_color2'] ?? '', '#1e40af');
    $out['cta_call_label']       = cms_sanitize_cta_label($current['cta_call_label'] ?? '', 'Call');
    $preserveContact = [
        'contact_form_to_email', 'contact_form_subject',
        'contact_form_use_custom', 'contact_form_fields',
        'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass',
        'smtp_from_email', 'smtp_from_name',
    ];
    foreach ($preserveContact as $pk) {
        if (array_key_exists($pk, $current)) {
            $out[$pk] = $current[$pk];
        }
    }
    if (!is_dir(CMS_DATA_DIR)) {
        mkdir(CMS_DATA_DIR, 0755, true);
    }
    file_put_contents(CMS_DATA_DIR . 'site_settings.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    cms_invalidate_site_settings();
}

/**
 * Update phone / WhatsApp for public Call now & sticky CTAs (full settings file preserved).
 */
function cms_sticky_cta_layout() {
    return (getSiteSettings()['sticky_cta_layout'] ?? 'split') === 'full' ? 'full' : 'split';
}

function cms_cta_call_enabled() {
    return !empty(getSiteSettings()['cta_enable_call']);
}

function cms_cta_whatsapp_enabled() {
    return !empty(getSiteSettings()['cta_enable_whatsapp']);
}

function cms_cta_any_enabled() {
    return cms_cta_call_enabled() || cms_cta_whatsapp_enabled();
}

function cms_cta_sticky_desktop() {
    return !empty(getSiteSettings()['cta_sticky_desktop']);
}

/**
 * Normalize to #rrggbb for safe use in CSS; invalid input returns $default.
 */
function cms_sanitize_hex_color($value, $default = '#1d4ed8') {
    $v = trim((string) $value);
    if (preg_match('/^#([0-9A-Fa-f]{6})$/', $v, $m)) {
        return '#' . strtolower($m[1]);
    }
    if (preg_match('/^#([0-9A-Fa-f]{3})$/', $v, $m)) {
        $h = $m[1];
        return '#' . strtolower($h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2]);
    }
    return $default;
}

/** Short label for the Call CTA; safe for HTML text and title attributes. */
function cms_sanitize_cta_label($value, $default = 'Call') {
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', trim((string) $value));
    if ($s === '') {
        return $default;
    }
    $s = preg_replace('/\s+/u', ' ', $s);
    if (function_exists('mb_substr')) {
        $s = mb_substr($s, 0, 32, 'UTF-8');
    } else {
        $s = substr($s, 0, 32);
    }
    $s = trim($s);
    return $s !== '' ? $s : $default;
}

function cms_cta_call_label() {
    return cms_sanitize_cta_label(getSiteSettings()['cta_call_label'] ?? '', 'Call');
}

/** @return int[] RGB 0–255 from a hex color (after sanitizing). */
function cms_hex_to_rgb_channels($hex) {
    $hex = ltrim(cms_sanitize_hex_color($hex, '#1d4ed8'), '#');
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

/** CSS rgba() string for glow / pulse animations tied to the Call button color. */
function cms_hex_rgba_css($hex, $alpha) {
    [$r, $g, $b] = cms_hex_to_rgb_channels($hex);
    $a = max(0.0, min(1.0, (float) $alpha));
    return sprintf('rgba(%d,%d,%d,%.2f)', $r, $g, $b, $a);
}

/** @return array{0:string,1:string} gradient stops for the Call CTA */
function cms_cta_call_gradient_colors() {
    $s = getSiteSettings();
    $a = cms_sanitize_hex_color($s['cta_call_color'] ?? '', '#1d4ed8');
    $b = cms_sanitize_hex_color($s['cta_call_color2'] ?? '', '#1e40af');
    return [$a, $b];
}

function cms_save_contact_settings($phone, $whatsapp, $layout, array $flags) {
    $cur = getSiteSettings();
    $cur['phone']    = trim((string) $phone);
    $cur['whatsapp'] = trim((string) $whatsapp);
    $cur['sticky_cta_layout'] = ($layout === 'full') ? 'full' : 'split';
    $cur['cta_enable_call']     = !empty($flags['cta_enable_call']);
    $cur['cta_enable_whatsapp'] = !empty($flags['cta_enable_whatsapp']);
    $cur['cta_sticky_desktop']  = !empty($flags['cta_sticky_desktop']);
    $prevC1 = cms_sanitize_hex_color($cur['cta_call_color'] ?? '', '#1d4ed8');
    $prevC2 = cms_sanitize_hex_color($cur['cta_call_color2'] ?? '', '#1e40af');
    $cur['cta_call_color'] = cms_sanitize_hex_color(
        array_key_exists('cta_call_color', $flags) ? (string) $flags['cta_call_color'] : ($cur['cta_call_color'] ?? ''),
        $prevC1
    );
    $cur['cta_call_color2'] = cms_sanitize_hex_color(
        array_key_exists('cta_call_color2', $flags) ? (string) $flags['cta_call_color2'] : ($cur['cta_call_color2'] ?? ''),
        $prevC2
    );
    $prevLbl = cms_sanitize_cta_label($cur['cta_call_label'] ?? '', 'Call');
    $cur['cta_call_label'] = array_key_exists('cta_call_label', $flags)
        ? cms_sanitize_cta_label((string) $flags['cta_call_label'], 'Call')
        : $prevLbl;
    if (!is_dir(CMS_DATA_DIR)) {
        mkdir(CMS_DATA_DIR, 0755, true);
    }
    file_put_contents(CMS_DATA_DIR . 'site_settings.json', json_encode($cur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    cms_invalidate_site_settings();
}

function cms_is_maintenance_mode() {
    return getSiteSettings()['maintenance_mode'] === true;
}

/**
 * True when this request is handled by a back-office script (not the public site).
 * Admins were previously exempt via session on index.php/view.php, which made
 * maintenance look "broken" in the same browser used to enable it.
 */
function cms_is_admin_area_request() {
    $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
    static $adminScripts = [
        'admin.php',
        'media_manager.php',
        'backup.php',
        'download_page.php',
        'hard_restore.php',
        'panel.php',
        'crm_mark_call.php',
    ];
    return in_array($script, $adminScripts, true);
}

function cms_public_should_show_maintenance() {
    if (!cms_is_maintenance_mode()) {
        return false;
    }
    if (cms_is_admin_area_request()) {
        return false;
    }
    $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === 'contact_submit.php') {
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
    <script type="application/ld+json"><?php echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?></script>
    <?php
    $analytics = trim((string) (getSiteSettings()['analytics_head_html'] ?? ''));
    if ($analytics !== '') {
        echo $analytics . "\n";
    }
}

/** Echo trusted raw HTML from site settings (editable only in admin). */
function cms_echo_site_html_snippet(string $settingKey) {
    $h = trim((string) (getSiteSettings()[$settingKey] ?? ''));
    if ($h !== '') {
        echo $h . "\n";
    }
}

function cms_nav_page_links_html() {
    include_once __DIR__ . '/cms_core.php';
    $html = '';
    foreach (getAllCMSPages() as $p) {
        if ($p['is_home'] ?? false) {
            continue;
        }
        if (($p['status'] ?? 'draft') !== 'published') {
            continue;
        }
        $label = ucwords(str_replace('-', ' ', $p['slug']));
        $html .= '<a class="nav-page-link" href="' . cms_escape(cms_page_url($p['slug'])) . '">' . cms_escape($label) . '</a>';
    }
    return $html;
}

function getHeader($title) {
    [$callC1, $callC2] = cms_cta_call_gradient_colors();
    $pulseRing  = cms_hex_rgba_css($callC1, 0.5);
    $pulseFade  = cms_hex_rgba_css($callC1, 0);
    $pulseGlow  = cms_hex_rgba_css($callC1, 0.32);
    $pulseGlow2 = cms_hex_rgba_css($callC1, 0.14);
    $brand      = cms_brand();
    $phone      = cms_phone();
    $wa         = cms_whatsapp();
    $telDigits  = cms_phone_tel_digits();
    $waDigits   = cms_whatsapp_digits();
    $telHref    = 'tel:' . $telDigits;
    $waHref     = 'https://wa.me/' . $waDigits;
    $waPrefill  = rawurlencode('Hi, I found you on ' . $brand . ' and would like to get in touch.');
    $waHrefMsg  = $waHref . '?text=' . $waPrefill;
    $pagesLinks = cms_nav_page_links_html();

    $showCall  = cms_cta_call_enabled();
    $showWa    = cms_cta_whatsapp_enabled();
    $ctaCount  = (int) $showCall + (int) $showWa;
    $ctaLayout = cms_sticky_cta_layout();
    $stickyDesktop = cms_cta_sticky_desktop();
    $callLabel     = cms_cta_call_label();
    $logoSrc       = cms_header_logo_url_resolved();
    $headerSub     = cms_site_tagline();
    $navBrandMod   = ($logoSrc !== '' || $headerSub !== '') ? ' nav-brand--rich' : ' nav-brand--gradient';
    ?>
    <style id="cms-cta-call-theme">:root{--cms-cta-call-a:<?php echo cms_escape($callC1); ?>;--cms-cta-call-b:<?php echo cms_escape($callC2); ?>;--cms-cta-call-pulse:<?php echo cms_escape($pulseRing); ?>;--cms-cta-call-pulse-fade:<?php echo cms_escape($pulseFade); ?>;--cms-cta-call-pulse-glow:<?php echo cms_escape($pulseGlow); ?>;--cms-cta-call-pulse-glow2:<?php echo cms_escape($pulseGlow2); ?>;}</style>
    <header class="glass-nav" role="banner">
        <a href="<?php echo cms_escape(cms_home_url()); ?>" class="nav-brand<?php echo $navBrandMod; ?>" aria-label="<?php echo cms_escape($brand . ($headerSub !== '' ? ' ' . $headerSub : '')); ?>">
            <?php if ($logoSrc !== ''): ?>
            <img class="nav-brand__logo" src="<?php echo cms_escape($logoSrc); ?>" alt="" width="512" height="515" decoding="async" sizes="54px">
            <?php endif; ?>
            <span class="nav-brand__text">
                <span class="nav-brand__name"><?php echo cms_escape($brand); ?></span>
                <?php if ($headerSub !== ''): ?>
                <span class="nav-brand__sub"><?php echo cms_escape($headerSub); ?></span>
                <?php endif; ?>
            </span>
        </a>

        <button type="button" class="nav-menu-toggle" id="nav-menu-toggle" aria-expanded="false" aria-controls="site-nav-drawer" aria-label="Open menu">
            <span class="nav-menu-bar" aria-hidden="true"></span>
            <span class="nav-menu-bar" aria-hidden="true"></span>
            <span class="nav-menu-bar" aria-hidden="true"></span>
        </button>

        <nav class="nav-links nav-links--desktop" aria-label="Primary">
            <a class="nav-page-link" href="<?php echo cms_escape(cms_home_url()); ?>">Home</a>
            <?php echo $pagesLinks; ?>
        </nav>

        <?php if ($ctaCount > 0): ?>
        <div class="nav-cta nav-cta--desktop nav-cta--dup-sticky nav-cta--<?php echo cms_escape($ctaLayout); ?> nav-cta--count-<?php echo (string) $ctaCount; ?>" role="group" aria-label="Contact">
            <?php if ($showCall): ?>
            <a class="nav-sticky-cta__btn nav-sticky-cta__btn--call cta-pulse" href="<?php echo cms_escape($telHref); ?>"
               data-cta="call" data-contact="<?php echo cms_escape($telDigits); ?>"
               title="<?php echo cms_escape($callLabel . ' ' . $phone); ?>">
                <?php echo cms_cta_phone_svg(); ?>
                <span><?php echo cms_escape($callLabel); ?></span>
            </a>
            <?php endif; ?>
            <?php if ($showWa): ?>
            <a class="nav-sticky-cta__btn nav-sticky-cta__btn--wa" href="<?php echo cms_escape($waHrefMsg); ?>" target="_blank" rel="noopener noreferrer"
               data-cta="whatsapp" data-contact="<?php echo cms_escape($waDigits); ?>"
               title="WhatsApp">
                <?php echo cms_cta_whatsapp_svg(); ?>
                <span>WhatsApp</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>

    <div class="nav-drawer-overlay" id="nav-drawer-overlay" aria-hidden="true"></div>
    <aside class="nav-drawer" id="site-nav-drawer" role="dialog" aria-modal="true" aria-label="Site menu" inert>
        <div class="nav-drawer__head<?php echo ($logoSrc !== '' || $headerSub !== '') ? ' nav-drawer__head--rich' : ''; ?>">
            <div class="nav-drawer__brand">
                <?php if ($logoSrc !== ''): ?>
                <img class="nav-drawer__brand-logo" src="<?php echo cms_escape($logoSrc); ?>" alt="" width="512" height="515" decoding="async" sizes="44px">
                <?php endif; ?>
                <div class="nav-drawer__brand-text">
                    <span class="nav-drawer__title"><?php echo cms_escape($brand); ?></span>
                    <?php if ($headerSub !== ''): ?>
                    <span class="nav-drawer__subtitle"><?php echo cms_escape($headerSub); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <button type="button" class="nav-drawer__close" id="nav-drawer-close" aria-label="Close menu">&times;</button>
        </div>
        <nav class="nav-drawer__links" aria-label="Mobile primary">
            <a class="nav-page-link nav-drawer__link" href="<?php echo cms_escape(cms_home_url()); ?>">Home</a>
            <?php echo str_replace('class="nav-page-link"', 'class="nav-page-link nav-drawer__link"', $pagesLinks); ?>
        </nav>
        <?php if ($ctaCount > 0): ?>
        <div class="nav-drawer__cta nav-cta--dup-sticky nav-cta--<?php echo cms_escape($ctaLayout); ?> nav-cta--count-<?php echo (string) $ctaCount; ?>" role="group" aria-label="Contact">
            <?php if ($showCall): ?>
            <a class="nav-sticky-cta__btn nav-sticky-cta__btn--call cta-pulse" href="<?php echo cms_escape($telHref); ?>"
               data-cta="call" data-contact="<?php echo cms_escape($telDigits); ?>"
               title="<?php echo cms_escape($callLabel . ' ' . $phone); ?>">
                <?php echo cms_cta_phone_svg(); ?>
                <span><?php echo cms_escape($callLabel); ?></span>
            </a>
            <?php endif; ?>
            <?php if ($showWa): ?>
            <a class="nav-sticky-cta__btn nav-sticky-cta__btn--wa" href="<?php echo cms_escape($waHrefMsg); ?>" target="_blank" rel="noopener noreferrer"
               data-cta="whatsapp" data-contact="<?php echo cms_escape($waDigits); ?>">
                <?php echo cms_cta_whatsapp_svg(); ?>
                <span>WhatsApp</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </aside>

    <?php if ($ctaCount > 0): ?>
    <div class="nav-sticky-cta nav-sticky-cta--<?php echo cms_escape($ctaLayout); ?><?php echo $stickyDesktop ? ' nav-sticky-cta--also-desktop' : ''; ?>"
         role="region" aria-label="Quick contact" data-sticky-layout="<?php echo cms_escape($ctaLayout); ?>"
         <?php echo $stickyDesktop ? 'data-sticky-desktop="1"' : ''; ?>>
        <?php if ($showCall): ?>
        <a class="nav-sticky-cta__btn nav-sticky-cta__btn--call cta-pulse" href="<?php echo cms_escape($telHref); ?>"
           data-cta="call" data-contact="<?php echo cms_escape($telDigits); ?>"
           title="<?php echo cms_escape($callLabel . ' ' . $phone); ?>">
            <?php echo cms_cta_phone_svg(); ?>
            <span><?php echo cms_escape($callLabel); ?></span>
        </a>
        <?php endif; ?>
        <?php if ($showWa): ?>
        <a class="nav-sticky-cta__btn nav-sticky-cta__btn--wa" href="<?php echo cms_escape($waHrefMsg); ?>" target="_blank" rel="noopener noreferrer"
           data-cta="whatsapp" data-contact="<?php echo cms_escape($waDigits); ?>">
            <?php echo cms_cta_whatsapp_svg(); ?>
            <span>WhatsApp</span>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
}

function cms_cta_phone_svg() {
    return '<svg class="cta-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" fill="currentColor"/></svg>';
}

function cms_cta_whatsapp_svg() {
    return '<svg class="cta-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.883 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" fill="currentColor"/></svg>';
}

function getPanel() {
    ?>
    <div id="control-panel">
        <button onclick="togglePanel(false)" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: #555; cursor: pointer; font-size: 24px;">&times;</button>
        <h2 style="margin-bottom: 20px; font-size: 24px; font-weight: 700; background: linear-gradient(45deg, #00f2fe, #4facfe); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Admin Control Panel</h2>
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
