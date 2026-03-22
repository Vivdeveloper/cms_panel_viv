<?php
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
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?err=csrf');
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
    header('Location: admin.php?trashed=1');
    exit;
}

// --- POST: restore page from trash ---
if (isset($_POST['post_restore_page'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=trash&err=csrf');
        exit;
    }
    global $trashDir;
    $basename = basename((string) ($_POST['trash_file'] ?? ''));
    if (!cms_is_safe_trash_basename($basename)) {
        header('Location: admin.php?tab=trash');
        exit;
    }
    $src = $trashDir . $basename;
    if (!is_file($src)) {
        header('Location: admin.php?tab=trash');
        exit;
    }
    $content = json_decode((string) file_get_contents($src), true);
    if (!is_array($content)) {
        header('Location: admin.php?tab=trash');
        exit;
    }
    $slug = cms_sanitize_slug($content['slug'] ?? '');
    if ($slug === '') {
        header('Location: admin.php?tab=trash');
        exit;
    }
    $dest = $pagesDir . $slug . '.json';
    if (is_file($dest)) {
        header('Location: admin.php?tab=trash&err=restore_slug_exists');
        exit;
    }
    $content['slug'] = $slug;
    file_put_contents($src, json_encode($content));
    rename($src, $dest);
    header('Location: admin.php?tab=trash&restored=1');
    exit;
}

// --- POST: permanently delete page from trash ---
if (isset($_POST['post_permanent_delete_page'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=trash&err=csrf');
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
    header('Location: admin.php?tab=trash&permanently_deleted=1');
    exit;
}

// --- POST: site settings & admin password & force patch ---
if (isset($_POST['save_site_settings'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=settings&err=csrf');
        exit;
    }
    cms_save_site_settings($_POST);
    header('Location: admin.php?tab=settings&settings_saved=1');
    exit;
}

if (isset($_POST['save_contact_cta'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=contact&err=csrf');
        exit;
    }
    $enCall = isset($_POST['cta_enable_call']) && $_POST['cta_enable_call'] === '1';
    $enWa   = isset($_POST['cta_enable_whatsapp']) && $_POST['cta_enable_whatsapp'] === '1';
    if (!$enCall && !$enWa) {
        header('Location: admin.php?tab=contact&err=cta_none');
        exit;
    }
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
    header('Location: admin.php?tab=contact&contact_saved=1');
    exit;
}

if (isset($_POST['change_admin_password'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=users&err=csrf');
        exit;
    }
    $a = (string) ($_POST['new_admin_password'] ?? '');
    $b = (string) ($_POST['new_admin_password_confirm'] ?? '');
    if (strlen($a) < 8 || $a !== $b) {
        header('Location: admin.php?tab=users&pwd_err=1');
        exit;
    }
    cms_set_admin_password($a);
    header('Location: admin.php?tab=users&pwd_ok=1');
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
    $u = preg_replace('/[^a-zA-Z0-9._-]+/', '', $u);
    if ($u !== '') {
        createUser($u, cms_normalize_user_role($_POST['role'] ?? 'Normal User'));
        header('Location: admin.php?tab=users&user=' . rawurlencode($u));
        exit;
    }
    header('Location: admin.php?tab=users');
    exit;
}

if (isset($_POST['update_user_role'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=users&err=csrf');
        exit;
    }
    $uname = preg_replace('/[^a-zA-Z0-9._-]+/', '', (string) ($_POST['edit_username'] ?? ''));
    $newRole = cms_normalize_user_role($_POST['role'] ?? '');
    global $usersDir;
    $path = $usersDir . $uname . '.json';
    if ($uname === '' || !is_file($path)) {
        header('Location: admin.php?tab=users');
        exit;
    }
    $current = json_decode((string) file_get_contents($path), true);
    if (!is_array($current)) {
        header('Location: admin.php?tab=users');
        exit;
    }
    $wasAdmin = cms_user_role_is_administrator($current['role'] ?? '');
    if ($wasAdmin && $newRole !== 'Administrator' && cms_count_administrators() <= 1) {
        header('Location: admin.php?tab=users&user=' . rawurlencode($uname) . '&err=last_admin');
        exit;
    }
    cms_update_user_role($uname, $newRole);
    header('Location: admin.php?tab=users&user=' . rawurlencode($uname) . '&user_updated=1');
    exit;
}

if (isset($_POST['delete_user'])) {
    checkAdmin();
    if (!cms_verify_csrf_post()) {
        header('Location: admin.php?tab=users&err=csrf');
        exit;
    }
    $uname = preg_replace('/[^a-zA-Z0-9._-]+/', '', (string) ($_POST['delete_username'] ?? ''));
    if (strtolower($uname) === 'admin') {
        header('Location: admin.php?tab=users&err=cannot_delete_admin');
        exit;
    }
    global $usersDir;
    $path = $usersDir . $uname . '.json';
    if (!is_file($path)) {
        header('Location: admin.php?tab=users');
        exit;
    }
    $current = json_decode((string) file_get_contents($path), true);
    if (is_array($current) && cms_user_role_is_administrator($current['role'] ?? '') && cms_count_administrators() <= 1) {
        header('Location: admin.php?tab=users&err=last_admin');
        exit;
    }
    cms_delete_user_file($uname);
    header('Location: admin.php?tab=users&user_deleted=1');
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
    $file = $pagesDir . $slug . '.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

// --- USER MANAGEMENT ---
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
    $username = preg_replace('/[^a-zA-Z0-9._-]+/', '', (string) $username);
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

function cms_delete_user_file($username) {
    global $usersDir;
    $username = preg_replace('/[^a-zA-Z0-9._-]+/', '', (string) $username);
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

function createUser($username, $role) {
    global $usersDir;
    $userData = [
        'username' => $username,
        'role'     => cms_normalize_user_role($role),
        'created'  => date('Y-m-d'),
    ];
    file_put_contents($usersDir . $username . '.json', json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function getAllUsers() {
    global $usersDir;
    $users = [];
    $files = glob($usersDir . '*.json');
    foreach ($files as $file) {
        $row = json_decode((string) file_get_contents($file), true);
        if (is_array($row) && isset($row['username'])) {
            $users[] = $row;
        }
    }
    return $users;
}

function cms_get_user($username) {
    global $usersDir;
    $username = preg_replace('/[^a-zA-Z0-9._-]+/', '', (string) $username);
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

if (count(getAllUsers()) === 0) {
    createUser('admin', 'Administrator');
    createUser('user1', 'Normal User');
}
?>
