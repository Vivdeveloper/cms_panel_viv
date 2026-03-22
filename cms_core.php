<?php
session_start();
// Secure CMS Core (Flat-File)
$pagesDir = __DIR__ . '/pages_data/';
$usersDir = __DIR__ . '/users_data/';
$versionFile = $pagesDir . 'system_version.json';
$historyFile = $pagesDir . 'release_history.json';

if (!is_dir($pagesDir)) mkdir($pagesDir, 0777, true);
if (!is_dir($usersDir)) mkdir($usersDir, 0777, true);

// --- ACCESS CONTROL GATEKEEPER ---
function checkAdmin() {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        header("Location: admin.php?error=unauthorized");
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

function bumpVersion($type = 'patch', $status = "System Update") {
    global $versionFile, $historyFile;
    $vData = getSystemVersion();
    $oldVer = $vData['ver'];
    $vParts = explode('.', $vData['ver']);
    
    if ($type == 'patch') $vParts[2]++;
    else if ($type == 'minor') { $vParts[1]++; $vParts[2] = 0; }
    
    $vData['ver'] = implode('.', $vParts);
    $vData['last_release'] = date('Y-m-d H:i:s');
    
    // Log history
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
    array_unshift($history, [
        'from' => $oldVer, 
        'to' => $vData['ver'], 
        'time' => $vData['last_release'],
        'git_status' => $status
    ]);
    file_put_contents($historyFile, json_encode(array_slice($history, 0, 10)));
    file_put_contents($versionFile, json_encode($vData));
    return $vData;
}

// --- PAGE ACTIONS ---
if (isset($_POST['create_page'])) {
    checkAdmin();
    $slug = strtolower(trim($_POST['slug']));
    
    // If updating existing, use hidden current_slug if slug box is empty (security)
    if (!$slug && isset($_POST['current_slug'])) $slug = $_POST['current_slug'];
    if (isset($_POST['current_slug']) && !empty($_POST['current_slug'])) $slug = $_POST['current_slug'];
    
    $html = $_POST['html_content'] ?? '';
    $css = $_POST['css_content'] ?? '';
    $isHome = isset($_POST['is_home']) ? true : false;
    
    if ($slug) {
        $pageData = [
            'slug' => $slug, 
            'html' => $html, 
            'css' => $css, 
            'is_home' => $isHome, 
            'updated' => date('Y-m-d H:i:s')
        ];
        
        // If this is becoming Home, remove Home flag from ALL others
        if ($isHome) {
            $files = glob($pagesDir . '*.json');
            foreach ($files as $f) {
                $fname = basename($f);
                if ($fname == 'system_version.json' || $fname == 'release_history.json') continue;
                
                $d = json_decode(file_get_contents($f), true);
                if(isset($d['is_home']) && $d['is_home'] === true) {
                    $d['is_home'] = false;
                    file_put_contents($f, json_encode($d));
                }
            }
        }
        
        file_put_contents($pagesDir . $slug . '.json', json_encode($pageData));
        bumpVersion('patch', "Update Design: $slug");
        
        // Return to admin without the obsolete logged_in param
        header("Location: admin.php?success=1");
        exit;
    }
}

if (isset($_GET['delete'])) {
    checkAdmin();
    $slug = $_GET['delete'];
    if (file_exists($pagesDir . $slug . '.json')) unlink($pagesDir . $slug . '.json');
    header("Location: admin.php?deleted=1");
    exit;
}

// --- DATA FETCHERS ---
function getAllCMSPages() {
    global $pagesDir;
    $pages = [];
    $files = glob($pagesDir . '*.json');
    foreach ($files as $file) {
        if (basename($file) == 'system_version.json' || basename($file) == 'release_history.json') continue;
        $content = json_decode(file_get_contents($file), true);
        if (isset($content['slug'])) $pages[] = $content;
    }
    return $pages;
}

function getCMSPage($slug) {
    global $pagesDir;
    $file = $pagesDir . $slug . '.json';
    if (file_exists($file)) return json_decode(file_get_contents($file), true);
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
    foreach ($files as $file) { $users[] = json_decode(file_get_contents($file), true); }
    return $users;
}

if (count(getAllUsers()) == 0) { createUser('admin', 'Admin'); createUser('user1', 'Normal'); }
?>
