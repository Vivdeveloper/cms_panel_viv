<?php
// Secure CMS Core (Flat-File)
$pagesDir = __DIR__ . '/pages_data/';
if (!is_dir($pagesDir)) mkdir($pagesDir, 0777, true);

// Create Page Logic
if (isset($_POST['create_page'])) {
    $slug = strtolower(trim($_POST['slug']));
    $html = $_POST['html_content'];
    $css = $_POST['css_content'];
    $isHome = isset($_POST['is_home']) ? true : false;
    
    if ($slug) {
        $pageData = [
            'slug' => $slug,
            'html' => $html,
            'css' => $css,
            'is_home' => $isHome,
            'updated' => date('Y-m-d H:i:s')
        ];
        
        // If this is set as home, reset others
        if ($isHome) {
            $files = glob($pagesDir . '*.json');
            foreach ($files as $f) {
                $d = json_decode(file_get_contents($f), true);
                $d['is_home'] = false;
                file_put_contents($f, json_encode($d));
            }
        }

        file_put_contents($pagesDir . $slug . '.json', json_encode($pageData));
        header("Location: admin.php?logged_in=1&success=1");
        exit;
    }
}

// Delete Page Logic
if (isset($_GET['delete'])) {
    if (file_exists($pagesDir . $slug . '.json')) {
        unlink($pagesDir . $slug . '.json');
    }
    header("Location: admin.php?logged_in=1&deleted=1");
    exit;
}

// Fetch All Pages
function getAllCMSPages() {
    global $pagesDir;
    $pages = [];
    $files = glob($pagesDir . '*.json');
    foreach ($files as $file) {
        $content = json_decode(file_get_contents($file), true);
        if (isset($content['slug'])) $pages[] = $content;
    }
    return $pages;
}

// Fetch Single Page Data
function getCMSPage($slug) {
    global $pagesDir;
    $file = $pagesDir . $slug . '.json';
    if (file_exists($file)) return json_decode(file_get_contents($file), true);
    return null;
}

// User Management (Flat-File)
$usersDir = __DIR__ . '/users_data/';
if (!is_dir($usersDir)) mkdir($usersDir, 0777, true);

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

// Initial Admin
if (count(getAllUsers()) == 0) { createUser('admin', 'Admin'); createUser('user1', 'Normal'); }
?>
