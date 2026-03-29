<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly'  => true,
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/config.php';

$pagesDir = __DIR__ . '/pages_data/';
$trashDir = $pagesDir . 'trash/';
$usersDir = __DIR__ . '/users_data/';
$versionFile = $pagesDir . 'system_version.json';
$historyFile = $pagesDir . 'release_history.json';

if (!is_dir($pagesDir)) {
    mkdir($pagesDir, 0755, true);
}
if (!is_dir($trashDir)) {
    mkdir($trashDir, 0755, true);
}
if (!is_dir($usersDir)) {
    mkdir($usersDir, 0755, true);
}

cms_init_admin_secrets();

function cms_init_admin_secrets() {
    global $pagesDir;
    $path = $pagesDir . 'admin_secrets.json';
    if (is_file($path)) {
        return;
    }
    $hash = password_hash('12345', PASSWORD_DEFAULT);
    file_put_contents($path, json_encode(['password_hash' => $hash], JSON_UNESCAPED_SLASHES));
}

/* ── Login brute-force protection ─────────────────────────────── */

function cms_login_attempts_file(): string {
    $dir = __DIR__ . '/pages_data/login_rate/';
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    return $dir . md5($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . '.json';
}

function cms_login_is_locked_out(): int {
    $f = cms_login_attempts_file();
    if (!is_file($f)) return 0;
    $d = json_decode((string) file_get_contents($f), true);
    if (!is_array($d)) return 0;
    $attempts = (int) ($d['attempts'] ?? 0);
    $last     = (int) ($d['last'] ?? 0);
    $diff     = time() - $last;
    if ($attempts >= 5 && $diff < 900) return 900 - $diff;
    if ($diff >= 900) { @unlink($f); return 0; }
    return 0;
}

function cms_login_attempts_record(): void {
    $f = cms_login_attempts_file();
    $d = ['attempts' => 0, 'last' => 0];
    if (is_file($f)) {
        $j = json_decode((string) file_get_contents($f), true);
        if (is_array($j)) $d = $j;
    }
    $d['attempts'] = ((int) ($d['attempts'] ?? 0)) + 1;
    $d['last'] = time();
    file_put_contents($f, json_encode($d));
}

function cms_login_attempts_reset(): void {
    $f = cms_login_attempts_file();
    if (is_file($f)) { @unlink($f); }
}

/* ── CSRF ────────────────────────────────────────────────────── */

function cms_csrf_token() {
    if (empty($_SESSION['cms_csrf_token'])) {
        $_SESSION['cms_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['cms_csrf_token'];
}

function cms_verify_csrf_post() {
    $t = $_POST['cms_csrf'] ?? '';
    return is_string($t) && isset($_SESSION['cms_csrf_token']) && hash_equals($_SESSION['cms_csrf_token'], $t);
}

function cms_verify_csrf_get() {
    $t = $_GET['cms_csrf'] ?? '';
    return is_string($t) && isset($_SESSION['cms_csrf_token']) && hash_equals($_SESSION['cms_csrf_token'], $t);
}

function cms_verify_admin_password($plain) {
    global $pagesDir;
    cms_init_admin_secrets();
    $path = $pagesDir . 'admin_secrets.json';
    $j = json_decode((string) file_get_contents($path), true);
    $hash = is_array($j) && isset($j['password_hash']) ? (string) $j['password_hash'] : '';
    return $hash !== '' && password_verify((string) $plain, $hash);
}

function cms_set_admin_password($plain) {
    global $pagesDir;
    cms_init_admin_secrets();
    $path = $pagesDir . 'admin_secrets.json';
    file_put_contents($path, json_encode(['password_hash' => password_hash((string) $plain, PASSWORD_DEFAULT)], JSON_UNESCAPED_SLASHES));
}

/**
 * Shared admin login / change-password rules.
 *
 * @return string|null Error key: short|long|weak
 */
function cms_admin_password_policy_error(string $plain): ?string {
    $len = function_exists('mb_strlen') ? mb_strlen($plain, 'UTF-8') : strlen($plain);
    if ($len < 8) {
        return 'short';
    }
    if ($len > 256) {
        return 'long';
    }
    if (!preg_match('/\pL/u', $plain)) {
        return 'weak';
    }
    if (!preg_match('/\d/u', $plain)) {
        return 'weak';
    }

    return null;
}

function cms_is_admin_preview() {
    return !empty($_SESSION['is_admin']);
}

function cms_skip_page_json($basename) {
    static $skip = [
        'system_version.json',
        'release_history.json',
        'site_settings.json',
        'admin_secrets.json',
        'contact_submissions.json',
    ];
    return in_array($basename, $skip, true);
}

/**
 * Basenames under pages_data that are CMS page JSON files (not system JSON).
 *
 * @return list<string>
 */
function cms_page_json_basenames(): array {
    global $pagesDir;
    $out = [];
    foreach (glob($pagesDir . '*.json') ?: [] as $file) {
        $bn = basename($file);
        if (cms_skip_page_json($bn)) {
            continue;
        }
        $content = json_decode((string) file_get_contents($file), true);
        if (is_array($content) && isset($content['slug'])) {
            $out[] = $bn;
        }
    }

    return $out;
}

/**
 * Write a full page record to pages_data/{slug}.json. If is_home is true, clears is_home on other page files.
 */
function cms_persist_page_record(array $pageData): void {
    global $pagesDir;
    $slug = isset($pageData['slug']) ? cms_sanitize_slug((string) $pageData['slug']) : '';
    if ($slug === '') {
        return;
    }
    $pageData['slug'] = $slug;

    if (!empty($pageData['is_home'])) {
        $files = glob($pagesDir . '*.json') ?: [];
        foreach ($files as $f) {
            if (cms_skip_page_json(basename($f))) {
                continue;
            }
            $d = json_decode((string) file_get_contents($f), true);
            if (!is_array($d)) {
                continue;
            }
            if (($d['slug'] ?? '') === $slug) {
                continue;
            }
            if (!empty($d['is_home'])) {
                $d['is_home'] = false;
                file_put_contents($f, json_encode($d));
            }
        }
    }

    file_put_contents($pagesDir . $slug . '.json', json_encode($pageData));
}

/**
 * Normalize a loose import row (e.g. from XML) into a page record. Returns null if slug is invalid.
 */
function cms_normalize_page_import_assoc(array $row): ?array {
    $slug = cms_sanitize_slug((string) ($row['slug'] ?? ''));
    if ($slug === '') {
        return null;
    }
    $title = trim((string) ($row['title'] ?? ''));
    if ($title === '') {
        $title = ucwords(str_replace('-', ' ', $slug));
    }
    $status = strtolower(trim((string) ($row['status'] ?? 'draft')));
    $status = ($status === 'published') ? 'published' : 'draft';
    $allowInMenu = true;
    if (array_key_exists('allow_in_menu', $row)) {
        $v = $row['allow_in_menu'];
        if (is_bool($v)) {
            $allowInMenu = $v;
        } else {
            $s = strtolower(trim((string) $v));
            $allowInMenu = !in_array($s, ['0', 'false', 'no', 'off', ''], true);
        }
    }
    $isHome = false;
    if (array_key_exists('is_home', $row)) {
        $v = $row['is_home'];
        if (is_bool($v)) {
            $isHome = $v;
        } else {
            $s = strtolower(trim((string) $v));
            $isHome = in_array($s, ['1', 'true', 'yes', 'on'], true);
        }
    }

    return [
        'slug'             => $slug,
        'title'            => $title,
        'html'             => (string) ($row['html'] ?? ''),
        'css'              => (string) ($row['css'] ?? ''),
        'is_home'          => $isHome,
        'allow_in_menu'    => $allowInMenu,
        'status'           => $status,
        'updated'          => date('Y-m-d H:i:s'),
        'meta_description' => trim((string) ($row['meta_description'] ?? '')),
        'og_image'         => trim((string) ($row['og_image'] ?? '')),
        'page_template'    => cms_normalize_page_template((string) ($row['page_template'] ?? 'default')),
    ];
}

/** @return 'default'|'full_width'|'canvas' */
function cms_normalize_page_template($raw): string {
    $t = strtolower(trim((string) $raw));
    if ($t === 'full_width' || $t === 'full-width' || $t === 'fullwidth') {
        return 'full_width';
    }
    if ($t === 'canvas') {
        return 'canvas';
    }

    return 'full_width';
}

/** Public body classes for template CSS (default | full width | canvas). */
function cms_page_template_body_classes(string $normalizedTpl): string {
    $t = cms_normalize_page_template($normalizedTpl);
    if ($t === 'full_width') {
        return 'cms-tpl-full-width';
    }
    if ($t === 'canvas') {
        return 'cms-tpl-canvas';
    }

    return 'cms-tpl-full-width';
}

// --- ACCESS CONTROL GATEKEEPER ---
function checkAdmin() {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        header('Location: viv-admin.php?error=unauthorized');
        exit;
    }
}

/**
 * True if the signed-in user may create, edit, trash, restore, or permanently delete pages.
 * Legacy sessions without cms_username are treated as full access.
 */
function cms_user_can_edit_pages(): bool {
    $row = cms_current_user_record();
    if ($row === null) {
        return true;
    }
    return cms_user_role_is_administrator($row['role'] ?? '');
}

function cms_require_pages_write(): void {
    checkAdmin();
    if (!cms_user_can_edit_pages()) {
        header('Location: viv-admin.php?err=read_only');
        exit;
    }
}

// --- VERSION MANAGEMENT ---
function getSystemVersion() {
    global $versionFile;
    if (!file_exists($versionFile)) {
        file_put_contents($versionFile, json_encode(['ver' => '1.0.0', 'last_release' => 'N/A']));
    }
    return json_decode(file_get_contents($versionFile), true);
}

function bumpVersion($type = 'patch', $status = 'System Update') {
    global $versionFile, $historyFile;
    $vData = getSystemVersion();
    $oldVer = $vData['ver'];
    $vParts = explode('.', $vData['ver']);

    if ($type === 'patch') {
        $vParts[2]++;
    } elseif ($type === 'minor') {
        $vParts[1]++;
        $vParts[2] = 0;
    }

    $vData['ver'] = implode('.', $vParts);
    $vData['last_release'] = date('Y-m-d H:i:s');

    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
    if (!is_array($history)) {
        $history = [];
    }
    array_unshift($history, [
        'from'       => $oldVer,
        'to'         => $vData['ver'],
        'time'       => $vData['last_release'],
        'git_status' => $status,
    ]);
    file_put_contents($historyFile, json_encode(array_slice($history, 0, 10)));
    file_put_contents($versionFile, json_encode($vData));
    return $vData;
}

/** Basename only; must be a JSON file inside trash (no path segments). */
function cms_is_safe_trash_basename($name) {
    if (!is_string($name) || $name === '' || strpos($name, "\0") !== false) {
        return false;
    }
    if (basename($name) !== $name) {
        return false;
    }
    return (bool) preg_match('/^[a-z0-9][a-z0-9._-]*\.json$/', $name);
}

// --- POST: move page to trash ---
if (isset($_POST['post_delete_page'])) {
    cms_require_pages_write();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?err=csrf');
        exit;
    }
    global $trashDir;
    $slug = cms_sanitize_slug($_POST['delete_slug'] ?? '');
    $src = $pagesDir . $slug . '.json';
    if ($slug !== '' && is_file($src)) {
        if (!is_dir($trashDir)) {
            mkdir($trashDir, 0755, true);
        }
        $dest = $trashDir . $slug . '.json';
        if (is_file($dest)) {
            $dest = $trashDir . $slug . '.' . gmdate('YmdHis') . '.' . bin2hex(random_bytes(3)) . '.json';
        }
        rename($src, $dest);
    }
    header('Location: viv-admin.php?trashed=1');
    exit;
}

// --- POST: toggle allow_in_menu from pages list ---
if (isset($_POST['post_toggle_menu'])) {
    cms_require_pages_write();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?err=csrf');
        exit;
    }
    $slug = cms_sanitize_slug($_POST['toggle_menu_slug'] ?? '');
    if ($slug === '') {
        header('Location: viv-admin.php');
        exit;
    }
    $path = $pagesDir . $slug . '.json';
    if (!is_file($path)) {
        header('Location: viv-admin.php');
        exit;
    }
    $d = json_decode((string) file_get_contents($path), true);
    if (!is_array($d)) {
        header('Location: viv-admin.php');
        exit;
    }
    $d['allow_in_menu'] = !cms_page_show_in_public_menu($d);
    $d['updated'] = date('Y-m-d H:i:s');
    file_put_contents($path, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    bumpVersion('patch', 'Toggle menu: ' . $slug);
    header('Location: viv-admin.php');
    exit;
}

// --- POST: restore page from trash ---
if (isset($_POST['post_restore_page'])) {
    cms_require_pages_write();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=trash&err=csrf');
        exit;
    }
    global $trashDir;
    $basename = basename((string) ($_POST['trash_file'] ?? ''));
    if (!cms_is_safe_trash_basename($basename)) {
        header('Location: viv-admin.php?tab=trash');
        exit;
    }
    $src = $trashDir . $basename;
    if (!is_file($src)) {
        header('Location: viv-admin.php?tab=trash');
        exit;
    }
    $content = json_decode((string) file_get_contents($src), true);
    if (!is_array($content)) {
        header('Location: viv-admin.php?tab=trash');
        exit;
    }
    $slug = cms_sanitize_slug($content['slug'] ?? '');
    if ($slug === '') {
        header('Location: viv-admin.php?tab=trash');
        exit;
    }
    $dest = $pagesDir . $slug . '.json';
    if (is_file($dest)) {
        header('Location: viv-admin.php?tab=trash&err=restore_slug_exists');
        exit;
    }
    $content['slug'] = $slug;
    file_put_contents($src, json_encode($content));
    rename($src, $dest);
    header('Location: viv-admin.php?tab=trash&restored=1');
    exit;
}

// --- POST: permanently delete page from trash ---
if (isset($_POST['post_permanent_delete_page'])) {
    cms_require_pages_write();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=trash&err=csrf');
        exit;
    }
    global $trashDir;
    $basename = basename((string) ($_POST['trash_file'] ?? ''));
    if (cms_is_safe_trash_basename($basename)) {
        $path = $trashDir . $basename;
        if (is_file($path)) {
            unlink($path);
        }
    }
    header('Location: viv-admin.php?tab=trash&permanently_deleted=1');
    exit;
}

// --- POST: empty trash ---
if (isset($_POST['post_empty_trash'])) {
    cms_require_pages_write();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=trash&err=csrf');
        exit;
    }
    global $trashDir;
    if (is_dir($trashDir)) {
        foreach (scandir($trashDir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            if (cms_is_safe_trash_basename($f)) {
                $path = $trashDir . $f;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }
    header('Location: viv-admin.php?tab=trash&trash_emptied=1');
    exit;
}

// --- POST: site settings & admin password & force patch ---
if (isset($_POST['save_site_settings'])) {
    checkAdmin();
    $returnTab = isset($_POST['admin_return_tab']) ? (string) $_POST['admin_return_tab'] : 'settings';
    $validReturnTabs = ['settings', 'html_tags'];
    if (!in_array($returnTab, $validReturnTabs, true)) {
        $returnTab = 'settings';
    }
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=' . rawurlencode($returnTab) . '&err=csrf');
        exit;
    }
    $post = $_POST;
    $headerLogoUploadFailed = false;
    $headerLogoUploadedOk = false;
    $hf = $_FILES['header_logo_file'] ?? null;
    if (is_array($hf) && array_key_exists('error', $hf)) {
        $upErr = (int) $hf['error'];
        if ($upErr === UPLOAD_ERR_OK) {
            $uploaded = cms_handle_header_logo_upload($hf);
            if ($uploaded !== null) {
                $post['header_logo_url'] = $uploaded;
                $headerLogoUploadedOk = true;
            } else {
                $headerLogoUploadFailed = true;
            }
        } elseif ($upErr !== UPLOAD_ERR_NO_FILE) {
            $headerLogoUploadFailed = true;
        }
    }
    if (!empty($_POST['header_logo_clear']) && !$headerLogoUploadedOk) {
        $post['header_logo_url'] = '';
    }
    cms_save_site_settings($post);
    if ($headerLogoUploadFailed) {
        header('Location: viv-admin.php?tab=' . rawurlencode($returnTab) . '&err=header_logo_upload');
    } else {
        header('Location: viv-admin.php?tab=' . rawurlencode($returnTab) . '&settings_saved=1');
    }
    exit;
}

if (isset($_POST['save_contact_cta'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=contact&err=csrf');
        exit;
    }
    $enCall = isset($_POST['cta_enable_call']) && $_POST['cta_enable_call'] === '1';
    $enWa   = isset($_POST['cta_enable_whatsapp']) && $_POST['cta_enable_whatsapp'] === '1';
    $layout = (string) ($_POST['sticky_cta_layout'] ?? 'split');
    $layout = ($layout === 'full') ? 'full' : 'split';
    cms_save_contact_settings(
        $_POST['contact_phone'] ?? '',
        $_POST['contact_whatsapp'] ?? '',
        $layout,
        [
            'cta_enable_call'     => $enCall,
            'cta_enable_whatsapp' => $enWa,
            'cta_sticky_desktop'  => isset($_POST['cta_sticky_desktop']) && $_POST['cta_sticky_desktop'] === '1',
            'cta_call_color'      => (string) ($_POST['cta_call_color'] ?? ''),
            'cta_call_color2'     => (string) ($_POST['cta_call_color2'] ?? ''),
            'cta_call_label'      => (string) ($_POST['cta_call_label'] ?? ''),
        ]
    );
    header('Location: viv-admin.php?tab=contact&contact_saved=1');
    exit;
}

if (isset($_POST['save_contact_form'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=contact_form&err=csrf');
        exit;
    }
    $cfFieldsErr = '';
    cms_save_contact_form_settings($_POST, $cfFieldsErr);
    if ($cfFieldsErr === 'invalid') {
        header('Location: viv-admin.php?tab=contact_form&contact_form_saved=1&contact_form_fields_err=1');
        exit;
    }
    header('Location: viv-admin.php?tab=contact_form&contact_form_saved=1');
    exit;
}

if (isset($_POST['crm_manual_status'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=crm&err=csrf');
        exit;
    }
    $sid = (string) ($_POST['crm_submission_id'] ?? '');
    $st = (string) ($_POST['crm_status'] ?? '');
    $crmManualOk = cms_crm_update_submission_status($sid, $st, true);
    $rf = (string) ($_POST['crm_return_filter'] ?? 'all');
    $allowedF = array_merge(['all'], cms_crm_status_values());
    if (!in_array($rf, $allowedF, true)) {
        $rf = 'all';
    }
    if (in_array($rf, ['new', 'followup'], true)) {
        $rf = 'pending';
    }
    $rq = trim((string) ($_POST['crm_return_q'] ?? ''));
    if (function_exists('mb_substr')) {
        $rq = mb_substr($rq, 0, 200, 'UTF-8');
    } else {
        $rq = substr($rq, 0, 200);
    }
    $rd = strtolower(trim((string) ($_POST['crm_return_date'] ?? 'all')));
    if (!in_array($rd, ['all', 'today', 'custom'], true)) {
        $rd = 'all';
    }
    $rdf = cms_crm_sanitize_date_ymd((string) ($_POST['crm_return_date_from'] ?? ''));
    $rdt = cms_crm_sanitize_date_ymd((string) ($_POST['crm_return_date_to'] ?? ''));
    $q = 'viv-admin.php?tab=crm&crm_filter=' . rawurlencode($rf) . '&crm_q=' . rawurlencode($rq)
        . '&crm_date=' . rawurlencode($rd)
        . '&crm_from=' . rawurlencode($rdf)
        . '&crm_to=' . rawurlencode($rdt);
    if ($crmManualOk) {
        $q .= '&crm_updated=1#crm-lead-' . $sid;
    } else {
        $q .= '&crm_locked=1#crm-lead-' . $sid;
    }
    header('Location: ' . $q);
    exit;
}

if (isset($_POST['crm_delete_lead'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=crm&err=csrf');
        exit;
    }
    if (!cms_user_can_edit_pages()) {
        header('Location: viv-admin.php?tab=crm&err=unauthorized');
        exit;
    }
    
    $sid = (string) ($_POST['crm_submission_id'] ?? '');
    $crmDeleteOk = cms_crm_delete_submission($sid);
    
    $rf = (string) ($_POST['crm_return_filter'] ?? 'all');
    $allowedF = array_merge(['all'], cms_crm_status_values());
    if (!in_array($rf, $allowedF, true)) {
        $rf = 'all';
    }
    if (in_array($rf, ['new', 'followup'], true)) {
        $rf = 'pending';
    }
    $rq = trim((string) ($_POST['crm_return_q'] ?? ''));
    if (function_exists('mb_substr')) {
        $rq = mb_substr($rq, 0, 200, 'UTF-8');
    } else {
        $rq = substr($rq, 0, 200);
    }
    $rd = strtolower(trim((string) ($_POST['crm_return_date'] ?? 'all')));
    if (!in_array($rd, ['all', 'today', 'custom'], true)) {
        $rd = 'all';
    }
    $rdf = cms_crm_sanitize_date_ymd((string) ($_POST['crm_return_date_from'] ?? ''));
    $rdt = cms_crm_sanitize_date_ymd((string) ($_POST['crm_return_date_to'] ?? ''));
    $q = 'viv-admin.php?tab=crm&crm_filter=' . rawurlencode($rf) . '&crm_q=' . rawurlencode($rq)
        . '&crm_date=' . rawurlencode($rd)
        . '&crm_from=' . rawurlencode($rdf)
        . '&crm_to=' . rawurlencode($rdt);
        
    if ($crmDeleteOk) {
        $q .= '&crm_deleted=1#crm-panel';
    } else {
        $q .= '&err=delete_failed#crm-panel';
    }
    header('Location: ' . $q);
    exit;
}

if (isset($_POST['change_admin_password'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=users&err=csrf');
        exit;
    }
    $a = (string) ($_POST['new_admin_password'] ?? '');
    $policy = cms_admin_password_policy_error($a);
    if ($policy !== null) {
        header('Location: viv-admin.php?tab=users&pwd_err=' . rawurlencode($policy));
        exit;
    }
    cms_set_admin_password($a);
    header('Location: viv-admin.php?tab=users&pwd_ok=1');
    exit;
}

if (isset($_POST['force_patch_release'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=config&err=csrf');
        exit;
    }
    bumpVersion('patch', 'Manual patch from admin');
    header('Location: viv-admin.php?tab=config&patched=1');
    exit;
}

/**
 * If pages_data/{slug}.json already exists, append -2, -3, … (WordPress-style) until unused.
 */
if (!function_exists('cms_allocate_unique_page_slug')) {
    function cms_allocate_unique_page_slug(string $desired): string {
        global $pagesDir;
        $s = cms_sanitize_slug($desired);
        if ($s === '') {
            return '';
        }
        $candidate = $s;
        $n = 2;
        while (is_file($pagesDir . $candidate . '.json')) {
            $candidate = $s . '-' . $n;
            $n++;
            if ($n > 1002) {
                $candidate = $s . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        return $candidate;
    }
}

// --- PAGE ACTIONS ---
if (isset($_POST['create_page'])) {
    cms_require_pages_write();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?err=csrf');
        exit;
    }
    $currentSlug = cms_sanitize_slug($_POST['current_slug'] ?? '');
    $newSlug     = cms_sanitize_slug($_POST['slug'] ?? '');
    $title       = trim((string) ($_POST['page_title'] ?? ''));

    if ($currentSlug !== '') {
        if ($newSlug === '') {
            $newSlug = $currentSlug;
        }
        if ($newSlug !== $currentSlug) {
            if (file_exists($pagesDir . $newSlug . '.json')) {
                header('Location: viv-admin.php?edit=' . rawurlencode($currentSlug) . '&err=slug_exists');
                exit;
            }
            $oldPath = $pagesDir . $currentSlug . '.json';
            if (file_exists($oldPath)) {
                if (!rename($oldPath, $pagesDir . $newSlug . '.json')) {
                    header('Location: viv-admin.php?edit=' . rawurlencode($currentSlug) . '&err=slug_rename');
                    exit;
                }
            }
        }
    } else {
        if ($newSlug === '') {
            $newSlug = cms_sanitize_slug($title);
        }
        if ($newSlug === '') {
            header('Location: viv-admin.php?err=slug_empty');
            exit;
        }
        if (is_file($pagesDir . $newSlug . '.json')) {
            $newSlug = cms_allocate_unique_page_slug($newSlug);
        }
    }

    $slug   = $newSlug;
    $html   = $_POST['html_content'] ?? '';
    // Single editor: styles and scripts are now combined in html_content.
    $css    = ''; 
    $isHome = isset($_POST['is_home']);
    $allowInMenu = isset($_POST['allow_in_menu']);
    $status = ($_POST['page_status'] ?? 'draft') === 'published' ? 'published' : 'draft';

    $metaDesc = trim((string) ($_POST['meta_description'] ?? ''));
    $ogImage  = trim((string) ($_POST['og_image'] ?? ''));
    $pageTpl  = cms_normalize_page_template($_POST['page_template'] ?? 'default');

    $pageData = [
        'slug'             => $slug,
        'title'            => $title ?: ucwords(str_replace('-', ' ', $slug)),
        'html'             => $html,
        'css'              => $css,
        'is_home'          => $isHome,
        'allow_in_menu'    => $allowInMenu,
        'status'           => $status,
        'updated'          => date('Y-m-d H:i:s'),
        'meta_description' => $metaDesc,
        'og_image'         => $ogImage,
        'page_template'    => $pageTpl,
    ];

    cms_persist_page_record($pageData);
    bumpVersion('patch', "Update Design: $slug");

    header('Location: viv-admin.php?edit=' . rawurlencode($slug) . '&saved=1');
    exit;
}

// --- POST: add user (metadata only) ---
if (isset($_POST['add_user'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=users&err=csrf');
        exit;
    }
    $u = preg_replace('/[^a-zA-Z0-9._@-]+/', '', trim((string) ($_POST['username'] ?? '')));
    if ($u !== '') {
        $path = __DIR__ . '/users_data/' . $u . '.json';
        if (is_file($path)) {
            header('Location: viv-admin.php?tab=users&err=user_exists');
            exit;
        }
        $role = cms_normalize_user_role($_POST['role'] ?? 'Normal User');
        $menuPost = isset($_POST['menu_allow']) && is_array($_POST['menu_allow']) ? $_POST['menu_allow'] : [];
        createUser($u, $role, cms_sanitize_menu_allow($menuPost));
        header('Location: viv-admin.php?tab=users&user=' . rawurlencode($u) . '&user_created=1');
        exit;
    }
    header('Location: viv-admin.php?tab=users');
    exit;
}

if (isset($_POST['update_user_role'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=users&err=csrf');
        exit;
    }
    $uname = preg_replace('/[^a-zA-Z0-9._@-]+/', '', (string) ($_POST['edit_username'] ?? ''));
    $newUname = preg_replace('/[^a-zA-Z0-9._@-]+/', '', (string) ($_POST['username'] ?? ''));
    $newRole = cms_normalize_user_role($_POST['role'] ?? '');
    global $usersDir;
    $path = $usersDir . $uname . '.json';
    if ($uname === '' || !is_file($path)) {
        header('Location: viv-admin.php?tab=users');
        exit;
    }
    if ($newUname === '') {
        header('Location: viv-admin.php?tab=users&user=' . urlencode($uname) . '&err=email_invalid');
        exit;
    }
    if ($uname !== $newUname && is_file($usersDir . $newUname . '.json')) {
        header('Location: viv-admin.php?tab=users&user=' . urlencode($uname) . '&err=email_taken');
        exit;
    }
    $current = json_decode((string) file_get_contents($path), true);
    if (!is_array($current)) {
        header('Location: viv-admin.php?tab=users');
        exit;
    }
    $wasAdmin = cms_user_role_is_administrator($current['role'] ?? '');
    if ($wasAdmin && $newRole !== 'Administrator' && cms_count_administrators() <= 1) {
        header('Location: viv-admin.php?tab=users&user=' . rawurlencode($uname) . '&err=last_admin');
        exit;
    }
    $menuPost = isset($_POST['menu_allow']) && is_array($_POST['menu_allow']) ? $_POST['menu_allow'] : [];
    cms_update_user_v2($uname, $newUname, $newRole, $menuPost);
    header('Location: viv-admin.php?tab=users&user=' . rawurlencode($newUname) . '&user_updated=1');
    exit;
}

if (isset($_POST['delete_user'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: viv-admin.php?tab=users&err=csrf');
        exit;
    }
    $euname = preg_replace('/[^a-zA-Z0-9._@-]+/', '', (string) ($_POST['delete_username'] ?? ''));
    if ($euname === '') {
        header('Location: viv-admin.php?tab=users');
        exit;
    }
    // Prevent deleting primary admin or self
    $isPrimary = (strtolower($euname) === 'admin' || strtolower($euname) === 'matmovie01@gmail.com');
    $isSelf = (isset($_SESSION['cms_username']) && $_SESSION['cms_username'] === $euname);
    
    if (!$isPrimary && !$isSelf) {
        global $usersDir;
        $path = $usersDir . $euname . '.json';
        if (is_file($path)) unlink($path);
        header('Location: viv-admin.php?tab=users&deleted=1');
        exit;
    }
    header('Location: viv-admin.php?tab=users&err=protected');
    exit;
}

// --- DATA FETCHERS ---
function getAllCMSPages() {
    global $pagesDir;
    $pages = [];
    $files = glob($pagesDir . '*.json');
    foreach ($files as $file) {
        if (cms_skip_page_json(basename($file))) {
            continue;
        }
        $content = json_decode(file_get_contents($file), true);
        if (isset($content['slug'])) {
            $pages[] = $content;
        }
    }
    return $pages;
}

/**
 * Which page JSON to render on index.php: marked Home (published, or draft while admin preview),
 * else published slug home/index, else most recently updated published page.
 * If a page is marked is_home but only exists as draft, public visitors get null (placeholder), not another page.
 */
function cms_resolve_home_page_for_index(array $pages): ?array {
    $preview = cms_is_admin_preview();

    foreach ($pages as $p) {
        if (!($p['is_home'] ?? false)) {
            continue;
        }
        $pub = (($p['status'] ?? 'draft') === 'published');
        if ($pub || $preview) {
            return $p;
        }
    }

    $hasHomeFlag = false;
    foreach ($pages as $p) {
        if ($p['is_home'] ?? false) {
            $hasHomeFlag = true;
            break;
        }
    }
    if ($hasHomeFlag && !$preview) {
        return null;
    }

    foreach (['home', 'index'] as $slug) {
        foreach ($pages as $p) {
            if (($p['slug'] ?? '') !== $slug) {
                continue;
            }
            $pub = (($p['status'] ?? 'draft') === 'published');
            if ($pub || $preview) {
                return $p;
            }
        }
    }

    $published = [];
    foreach ($pages as $p) {
        if (($p['status'] ?? 'draft') === 'published') {
            $published[] = $p;
        }
    }
    if ($published === []) {
        return null;
    }
    usort($published, function ($a, $b) {
        return strcmp((string) ($b['updated'] ?? ''), (string) ($a['updated'] ?? ''));
    });
    return $published[0];
}

function getTrashedCMSPages() {
    global $trashDir;
    if (!is_dir($trashDir)) {
        return [];
    }
    $files = glob($trashDir . '*.json');
    if (!is_array($files)) {
        return [];
    }
    $pages = [];
    foreach ($files as $file) {
        $content = json_decode((string) file_get_contents($file), true);
        if (!is_array($content) || !isset($content['slug'])) {
            continue;
        }
        $content['_trash_basename'] = basename($file);
        $pages[] = $content;
    }
    usort($pages, function ($a, $b) {
        return strcmp((string) ($b['updated'] ?? ''), (string) ($a['updated'] ?? ''));
    });
    return $pages;
}

function getCMSPage($slug) {
    global $pagesDir;
    $slug = cms_sanitize_slug((string) $slug);
    if ($slug === '') {
        return null;
    }
    $file = $pagesDir . $slug . '.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

// --- USER MANAGEMENT ---
/** Keys for admin sidebar / tab access (keep in sync with admin_menu.php labels). */
function cms_admin_menu_keys() {
    return ['pages', 'trash', 'media', 'backup', 'settings', 'html_tags', 'contact', 'contact_form', 'crm', 'users', 'config'];
}

function cms_sanitize_menu_allow($raw) {
    $keys = cms_admin_menu_keys();
    $flip = array_flip($keys);
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $k) {
        $k = (string) $k;
        if (isset($flip[$k])) {
            $out[$k] = true;
        }
    }
    return array_values(array_keys($out));
}

/** Default sidebar items for Normal User when menu_allow is missing or empty. */
function cms_default_menu_allow_normal() {
    return ['html_tags', 'contact', 'crm'];
}

function cms_session_username() {
    $u = $_SESSION['cms_username'] ?? '';
    return is_string($u) ? preg_replace('/[^a-zA-Z0-9._@-]+/', '', $u) : '';
}

/** Logged-in CMS user row from users_data, or null (legacy session without username). */
function cms_current_user_record() {
    $name = cms_session_username();
    if ($name === '') {
        return null;
    }
    $row = cms_get_user($name);
    return is_array($row) ? $row : null;
}

/**
 * Which nav keys this user may see. Legacy sessions (no cms_username) get full menu.
 */
function cms_user_allowed_menu_keys(?array $user) {
    if ($user === null) {
        return cms_admin_menu_keys();
    }
    if (cms_user_role_is_administrator($user['role'] ?? '')) {
        return cms_admin_menu_keys();
    }
    $allow = $user['menu_allow'] ?? null;
    if (!is_array($allow) || $allow === []) {
        return cms_default_menu_allow_normal();
    }
    $san = cms_sanitize_menu_allow($allow);
    return $san !== [] ? $san : cms_default_menu_allow_normal();
}

function cms_user_may_access_menu_key(?array $user, string $key) {
    return in_array($key, cms_user_allowed_menu_keys($user), true);
}

function cms_normalize_user_role($r) {
    $r = trim((string) $r);
    $legacy = [
        'Admin'  => 'Administrator',
        'Normal' => 'Normal User',
    ];
    if (isset($legacy[$r])) {
        return $legacy[$r];
    }
    if ($r === 'Administrator' || $r === 'Normal User') {
        return $r;
    }
    return 'Normal User';
}

function cms_user_role_is_administrator($role) {
    return cms_normalize_user_role($role) === 'Administrator';
}

function cms_count_administrators() {
    $n = 0;
    foreach (getAllUsers() as $u) {
        if (cms_user_role_is_administrator($u['role'] ?? '')) {
            $n++;
        }
    }
    return $n;
}

function cms_update_user_role($username, $role) {
    global $usersDir;
    $username = preg_replace('/[^a-zA-Z0-9._@-]+/', '', (string) $username);
    if ($username === '') {
        return false;
    }
    $path = $usersDir . $username . '.json';
    if (!is_file($path)) {
        return false;
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return false;
    }
    $data['username'] = $username;
    $data['role']     = cms_normalize_user_role($role);
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return true;
}

/**
 * Persist menu_allow for non-administrators; administrators get key removed (full access implied).
 */
function cms_update_user_v2($oldUsername, $newUsername, $role, array $menuPost) {
    global $usersDir;
    $oldUsername = preg_replace('/[^a-zA-Z0-9._@-]+/', '', (string) $oldUsername);
    $newUsername = preg_replace('/[^a-zA-Z0-9._@-]+/', '', (string) $newUsername);
    if ($oldUsername === '' || $newUsername === '') {
        return false;
    }
    $oldPath = $usersDir . $oldUsername . '.json';
    if (!is_file($oldPath)) {
        return false;
    }
    $data = json_decode((string) file_get_contents($oldPath), true);
    if (!is_array($data)) {
        return false;
    }
    $norm = cms_normalize_user_role($role);
    if (cms_user_role_is_administrator($norm)) {
        unset($data['menu_allow']);
    } else {
        $san = cms_sanitize_menu_allow($menuPost);
        $data['menu_allow'] = $san !== [] ? $san : cms_default_menu_allow_normal();
    }
    $data['username'] = $newUsername;
    $data['email']    = $newUsername;
    $data['role']     = $norm;
    if ($oldUsername !== $newUsername) {
        unlink($oldPath);
        if (isset($_SESSION['cms_username']) && $_SESSION['cms_username'] === $oldUsername) {
            $_SESSION['cms_username'] = $newUsername;
        }
    }
    file_put_contents($usersDir . $newUsername . '.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return true;
}

function cms_delete_user_file($username) {
    global $usersDir;
    $username = preg_replace('/[^a-zA-Z0-9._@-]+/', '', (string) $username);
    if ($username === '' || strtolower($username) === 'admin') {
        return false;
    }
    $path = $usersDir . $username . '.json';
    if (is_file($path)) {
        unlink($path);
        return true;
    }
    return false;
}

function createUser($username, $role, array $menuAllowPost = []) {
    global $usersDir;
    $norm = cms_normalize_user_role($role);
    $userData = [
        'username' => $username,
        'email'    => $username, // Username is the email
        'role'     => $norm,
        'created'  => date('Y-m-d'),
    ];
    if (!cms_user_role_is_administrator($norm)) {
        $san = cms_sanitize_menu_allow($menuAllowPost);
        $userData['menu_allow'] = $san !== [] ? $san : cms_default_menu_allow_normal();
    }
    file_put_contents($usersDir . $username . '.json', json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function getAllUsers() {
    global $usersDir;
    clearstatcache();
    $users = [];
    $files = glob($usersDir . '*.json');
    if (is_array($files)) {
        foreach ($files as $file) {
            $row = json_decode((string) file_get_contents($file), true);
            if (is_array($row) && isset($row['username'])) {
                $users[] = $row;
            }
        }
    }
    return $users;
}

function cms_get_user($username) {
    global $usersDir;
    $username = preg_replace('/[^a-zA-Z0-9._@-]+/', '', (string) $username);
    if ($username === '') {
        return null;
    }
    $path = $usersDir . $username . '.json';
    if (!is_file($path)) {
        return null;
    }
    $row = json_decode((string) file_get_contents($path), true);
    return is_array($row) ? $row : null;
}

/** Valid non-empty email, or empty string if invalid / empty input. */
function cms_sanitize_user_email($raw) {
    $s = trim((string) $raw);
    if ($s === '' || strlen($s) > 254) {
        return '';
    }
    return filter_var($s, FILTER_VALIDATE_EMAIL) ? $s : '';
}

/** True if $email (non-empty, normalized case) is already stored on another user. */
function cms_user_email_taken(string $email, string $exceptUsername): bool {
    $want = strtolower(trim($email));
    if ($want === '') {
        return false;
    }
    $ex = strtolower((string) $exceptUsername);
    foreach (getAllUsers() as $u) {
        $un = strtolower((string) ($u['username'] ?? ''));
        $em = strtolower(trim((string) ($u['email'] ?? '')));
        if ($em !== '' && $em === $want && $un !== $ex) {
            return true;
        }
    }
    return false;
}

/**
 * Resolve sign-in identifier: values containing @ match stored email (case-insensitive); otherwise username.
 *
 * @return array|null User row including username
 */
function cms_find_user_for_login(string $identifier): ?array {
    $identifier = trim($identifier);
    if ($identifier === '') {
        return null;
    }
    if (strpos($identifier, '@') !== false) {
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $want = strtolower($identifier);
        foreach (getAllUsers() as $u) {
            $em = strtolower(trim((string) ($u['email'] ?? '')));
            if ($em !== '' && $em === $want) {
                return $u;
            }
        }
        return null;
    }
    $u = preg_replace('/[^a-zA-Z0-9._@-]+/', '', $identifier);
    if ($u === '') {
        return null;
    }
    $row = cms_get_user($u);
    return is_array($row) ? $row : null;
}

if (count(getAllUsers()) === 0) {
    createUser('admin', 'Administrator');
    createUser('user1', 'Normal User');
}
?>
