<?php
session_start();

require_once __DIR__ . '/config.php';

$pagesDir = __DIR__ . '/pages_data/';
$usersDir = __DIR__ . '/users_data/';
$versionFile = $pagesDir . 'system_version.json';
$historyFile = $pagesDir . 'release_history.json';

if (!is_dir($pagesDir)) {
    mkdir($pagesDir, 0755, true);
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

function cms_is_admin_preview() {
    return !empty($_SESSION['is_admin']);
}

function cms_skip_page_json($basename) {
    static $skip = [
        'system_version.json',
        'release_history.json',
        'site_settings.json',
        'admin_secrets.json',
    ];
    return in_array($basename, $skip, true);
}

// --- ACCESS CONTROL GATEKEEPER ---
function checkAdmin() {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        header('Location: admin.php?error=unauthorized');
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

function cms_sanitize_slug($str) {
    $str = strtolower(trim(preg_replace('/\s+/', '-', (string) $str)));
    $str = preg_replace('/[^a-z0-9\-]/', '', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}

// --- POST: delete page ---
if (isset($_POST['post_delete_page'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?err=csrf');
        exit;
    }
    $slug = cms_sanitize_slug($_POST['delete_slug'] ?? '');
    if ($slug !== '' && file_exists($pagesDir . $slug . '.json')) {
        unlink($pagesDir . $slug . '.json');
    }
    header('Location: admin.php?deleted=1');
    exit;
}

// --- POST: site settings & admin password & force patch ---
if (isset($_POST['save_site_settings'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=config&err=csrf');
        exit;
    }
    cms_save_site_settings($_POST);
    header('Location: admin.php?tab=config&settings_saved=1');
    exit;
}

if (isset($_POST['change_admin_password'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=config&err=csrf');
        exit;
    }
    $a = (string) ($_POST['new_admin_password'] ?? '');
    $b = (string) ($_POST['new_admin_password_confirm'] ?? '');
    if (strlen($a) < 8 || $a !== $b) {
        header('Location: admin.php?tab=config&pwd_err=1');
        exit;
    }
    cms_set_admin_password($a);
    header('Location: admin.php?tab=config&pwd_ok=1');
    exit;
}

if (isset($_POST['force_patch_release'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=config&err=csrf');
        exit;
    }
    bumpVersion('patch', 'Manual patch from admin');
    header('Location: admin.php?tab=config&patched=1');
    exit;
}

// --- PAGE ACTIONS ---
if (isset($_POST['create_page'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?err=csrf');
        exit;
    }
    $currentSlug = cms_sanitize_slug($_POST['current_slug'] ?? '');
    $newSlug     = cms_sanitize_slug($_POST['slug'] ?? '');

    if ($currentSlug !== '') {
        if ($newSlug === '') {
            $newSlug = $currentSlug;
        }
        if ($newSlug !== $currentSlug) {
            if (file_exists($pagesDir . $newSlug . '.json')) {
                header('Location: admin.php?edit=' . rawurlencode($currentSlug) . '&err=slug_exists');
                exit;
            }
            $oldPath = $pagesDir . $currentSlug . '.json';
            if (file_exists($oldPath)) {
                if (!rename($oldPath, $pagesDir . $newSlug . '.json')) {
                    header('Location: admin.php?edit=' . rawurlencode($currentSlug) . '&err=slug_rename');
                    exit;
                }
            }
        }
    } else {
        if ($newSlug === '') {
            header('Location: admin.php?err=slug_empty');
            exit;
        }
        if (file_exists($pagesDir . $newSlug . '.json')) {
            header('Location: admin.php?err=slug_exists');
            exit;
        }
    }

    $slug   = $newSlug;
    $title  = trim($_POST['page_title'] ?? '');
    $html   = $_POST['html_content'] ?? '';
    $css    = $_POST['css_content'] ?? '';
    $isHome = isset($_POST['is_home']);
    $status = ($_POST['page_status'] ?? 'draft') === 'published' ? 'published' : 'draft';

    $metaDesc = trim((string) ($_POST['meta_description'] ?? ''));
    $ogImage  = trim((string) ($_POST['og_image'] ?? ''));

    $pageData = [
        'slug'             => $slug,
        'title'            => $title ?: ucwords(str_replace('-', ' ', $slug)),
        'html'             => $html,
        'css'              => $css,
        'is_home'          => $isHome,
        'status'           => $status,
        'updated'          => date('Y-m-d H:i:s'),
        'meta_description' => $metaDesc,
        'og_image'         => $ogImage,
    ];

    if ($isHome) {
        $files = glob($pagesDir . '*.json');
        foreach ($files as $f) {
            if (cms_skip_page_json(basename($f))) {
                continue;
            }

            $d = json_decode(file_get_contents($f), true);
            if (isset($d['is_home']) && $d['is_home'] === true) {
                $d['is_home'] = false;
                file_put_contents($f, json_encode($d));
            }
        }
    }

    file_put_contents($pagesDir . $slug . '.json', json_encode($pageData));
    bumpVersion('patch', "Update Design: $slug");

    header('Location: admin.php?edit=' . rawurlencode($slug) . '&saved=1');
    exit;
}

// --- POST: add user (metadata only) ---
if (isset($_POST['add_user'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=users&err=csrf');
        exit;
    }
    $u = trim((string) ($_POST['username'] ?? ''));
    if ($u !== '') {
        createUser($u, $_POST['role'] ?? 'Normal User');
    }
    header('Location: admin.php?tab=users');
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

function getCMSPage($slug) {
    global $pagesDir;
    $file = $pagesDir . $slug . '.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

// --- USER MANAGEMENT ---
function createUser($username, $role) {
    global $usersDir;
    $userData = ['username' => $username, 'role' => $role, 'created' => date('Y-m-d')];
    file_put_contents($usersDir . $username . '.json', json_encode($userData));
}

function getAllUsers() {
    global $usersDir;
    $users = [];
    $files = glob($usersDir . '*.json');
    foreach ($files as $file) {
        $users[] = json_decode(file_get_contents($file), true);
    }
    return $users;
}

if (count(getAllUsers()) === 0) {
    createUser('admin', 'Admin');
    createUser('user1', 'Normal');
}
?>
