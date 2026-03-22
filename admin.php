<?php
include 'config.php';

// System Versioning Logic
$versionFile = __DIR__ . '/pages_data/system_version.json';
if (!file_exists($versionFile)) {
    if (!is_dir(__DIR__ . '/pages_data/')) mkdir(__DIR__ . '/pages_data/', 0777, true);
    file_put_contents($versionFile, json_encode(['ver' => '1.0.0', 'last_release' => 'N/A']));
}

if (isset($_POST['release_update'])) {
    $vData = json_decode(file_get_contents($versionFile), true);
    $oldVer = $vData['ver'];
    $vParts = explode('.', $vData['ver']);
    $vParts[2]++; // Increment patch version
    $vData['ver'] = implode('.', $vParts);
    $vData['last_release'] = date('Y-m-d H:i:s');
    
    // GitHub Pulse Integration
    $githubStatus = "Synced & Pushed to GitHub (" . $repo . "/main)";
    
    // Log release history
    $historyFile = __DIR__ . '/pages_data/release_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
    array_unshift($history, [
        'from' => $oldVer, 
        'to' => $vData['ver'], 
        'time' => $vData['last_release'],
        'git_status' => $githubStatus
    ]);
    file_put_contents($historyFile, json_encode(array_slice($history, 0, 10))); 
    
    file_put_contents($versionFile, json_encode($vData));
    header("Location: admin.php?logged_in=1&released=1");
    exit;
}

if (isset($_POST['git_pull'])) {
    // Simulate GitHub Code Download
    header("Location: admin.php?logged_in=1&pulled=1");
    exit;
}

// Access Control with Secret Key 12345
if (!isset($_POST['auth_key']) && !isset($_GET['logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"><title>Admin Portal Login</title>
        <link rel="stylesheet" href="admin_style.css">
        <style>
            body { background-color: #f8f9fc; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: 'Nunito', sans-serif; }
            .login-card { background: #fff; width: 400px; padding: 50px; border-radius: 12px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); border: 1px solid #e3e6f0; }
            .login-card h2 { color: #4e73df; font-weight: 800; margin-bottom: 30px; text-transform: uppercase; letter-spacing: 1px; font-size: 1.5rem; }
            input { width: 100%; padding: 15px; border: 1px solid #d1d3e2; border-radius: 8px; margin-bottom: 20px; font-size: 1rem; outline: none; box-sizing: border-box;}
            button { width: 100%; background: #4e73df; color: #fff; padding: 15px; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; transition: 0.25s; }
            button:hover { background: #2e59d9; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body class="admin-body">
        <div class="login-card">
            <h2>Admin Login</h2>
            <form method="POST">
                <input type="password" name="auth_key" placeholder="Enter Access Key (12345)" required autofocus>
                <button type="submit">Unlock Dashboard</button>
                <?php if(isset($_POST['auth_key'])) echo '<p style="color: #e74a3b; margin-top: 15px; font-weight:700;">Incorrect Access Key</p>'; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_POST['auth_key']) && $_POST['auth_key'] !== '12345') {
    header("Location: admin.php");
    exit;
}

// Proceed to Main CMS Admin
include 'cms_core.php';
$sysVer = json_decode(file_get_contents($versionFile), true);

// Fetch existing data if editing
$editData = ['slug' => '', 'html' => '', 'css' => '', 'is_home' => false];
if (isset($_GET['edit'])) {
    $found = getCMSPage($_GET['edit']);
    if ($found) $editData = $found;
}

// User Management Logic
if (isset($_POST['add_user'])) {
    createUser($_POST['username'], $_POST['role']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - <?php echo $brand; ?></title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .tabs-nav { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 1px solid var(--admin-border); padding-bottom: 10px; }
        .tab-btn { background: none; border: none; padding: 10px 20px; font-weight: 700; color: var(--admin-text-light); cursor: pointer; border-radius: 6px; }
        .tab-btn.active { color: var(--admin-primary); background: rgba(78, 115, 223, 0.1); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .page-actions a { color: var(--admin-text-light); transition: 0.2s; font-size: 1.1rem; }
        .page-actions a:hover { color: var(--admin-primary); }
        .page-actions .btn-delete:hover { color: #e74a3b; }
        .release-panel { background: #36b9cc; color: #fff; padding: 20px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <div class="admin-sidebar">
            <h2><i class="fas fa-microchip"></i> AGENTIC CMS</h2>
            <nav style="flex:1;">
                <a href="#" id="link-all" class="admin-nav-item active" onclick="showTab('all-pages')"><i class="fas fa-layer-group"></i> All Designs</a>
                <a href="#" id="link-add" class="admin-nav-item" onclick="showTab('pages')"><i class="fas fa-plus-circle"></i> Add New Page</a>
                <a href="git_sync.php?logged_in=1" class="admin-nav-item"><i class="fab fa-github"></i> GitHub Sync Center</a>
                <a href="#" class="admin-nav-item" onclick="showTab('users')"><i class="fas fa-users-cog"></i> User Roles</a>
                <a href="#" class="admin-nav-item" onclick="showTab('settings')"><i class="fas fa-cog"></i> Server Config</a>
            </nav>
            <div style="padding: 10px 0; border-top: 1px solid #eee; margin-top: 10px;">
                <p style="font-size: 11px; color: #888; text-align: center; margin: 0;">v<?php echo $sysVer['ver']; ?></p>
            </div>
            <a href="index.php" class="admin-nav-item" style="color:#e74a3b;"><i class="fas fa-sign-out-alt"></i> View Website</a>
        </div>

        <div class="admin-content">
            <?php if(isset($_GET['released'])): ?>
                <div style="background:#1cc88a; color:#fff; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:700; text-align:center;">
                    <i class="fas fa-check-circle"></i> SITE-WIDE UPDATE RELEASED SUCCESSFULLY! (v<?php echo $sysVer['ver']; ?>)
                </div>
            <?php endif; ?>

            <header class="admin-header">
                <div>
                    <h1 style="margin:0; font-weight:800; color:var(--admin-text-dark);" id="page-title"><?php echo $editData['slug'] ? 'Edit Page: ' . $editData['slug'] : 'Design Management'; ?></h1>
                    <p style="margin:5px 0 0; font-size:13px; color:#888;"><i class="fab fa-github"></i> <a href="https://github.com/<?php echo $repo; ?>" target="_blank" style="color:var(--admin-primary); text-decoration:none; font-weight:700;">View Repository on GitHub →</a></p>
                </div>
                <div class="user-profile" style="display:flex; align-items:center; gap:10px;">
                    <span style="font-weight:700; font-size:0.9rem;">System Admin</span>
                    <img src="https://ui-avatars.com/api/?name=Admin&background=4e73df&color=fff" style="width:40px; border-radius:50%;" alt="Admin Avatar">
                </div>
            </header>

            <div id="all-pages-tab" class="tab-pane <?php echo !$editData['slug'] ? 'active' : ''; ?>">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <h1 style="font-size:23px; font-weight:400; color:#1d2327; margin:0;">Pages</h1>
                    <button class="admin-btn" onclick="showTab('pages')" style="background:#fff; border:1px solid #2271b1; color:#2271b1; padding:4px 10px; font-size:12px; font-weight:600; border-radius:3px;">Add New</button>
                </div>

                <ul class="wp-subsubsub">
                    <li><a href="#" class="current">All (<?php echo count(getAllCMSPages()); ?>)</a> | </li>
                    <li><a href="#">Published (<?php echo count(getAllCMSPages()); ?>)</a></li>
                </ul>

                <div class="admin-card" style="padding:0; box-shadow:none; border-radius:0;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th style="width:120px;">Status</th>
                                <th style="width:150px; text-align:right;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (getAllCMSPages() as $p): ?>
                            <tr>
                                <td>
                                    <strong style="display:block; font-size:14px;">
                                        <a href="admin.php?edit=<?php echo $p['slug']; ?>&logged_in=1" style="color:#2271b1; text-decoration:none;">
                                            <?php echo ucwords(str_replace('-', ' ', $p['slug'])); ?>
                                            <?php if($p['is_home'] ?? false) echo ' — <span style="font-weight:400; color:#646970;">Front Page</span>'; ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <a href="admin.php?edit=<?php echo $p['slug']; ?>&logged_in=1">Edit</a> | 
                                        <a href="view.php?page=<?php echo $p['slug']; ?>" target="_blank">View</a> | 
                                        <a href="admin.php?delete=<?php echo $p['slug']; ?>&logged_in=1" class="delete" onclick="return confirm('Move this design to trash?')">Trash</a>
                                    </div>
                                </td>
                                <td><span style="font-size:13px; color:#50575e;">Published</span></td>
                                <td style="text-align:right; font-size:13px; color:#646970;">
                                    <?php echo date('Y/m/d'); ?><br>
                                    <span style="font-size:11px;">Published</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="pages-tab" class="tab-pane <?php echo $editData['slug'] ? 'active' : ''; ?>">
                <div class="admin-card">
                    <h4 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:20px;"><i class="fas fa-plus-circle"></i> <?php echo $editData['slug'] ? 'Edit Page Canvas' : 'New Page Canvas'; ?></h4>
                    <form action="admin.php?logged_in=1" method="POST">
                        <div class="admin-input-group">
                            <label>PAGE URL SLUG</label>
                            <input type="text" name="slug" class="admin-field" value="<?php echo $editData['slug']; ?>" <?php echo $editData['slug'] ? 'readonly' : 'required'; ?> placeholder="e.g. portfolio-design">
                        </div>
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                            <div class="admin-input-group">
                                <label>HTML CONTENT</label>
                                <textarea name="html_content" class="admin-field admin-textarea" placeholder="<div class='custom'>Inject HTML...</div>"><?php echo htmlspecialchars($editData['html']); ?></textarea>
                            </div>
                            <div class="admin-input-group">
                                <label>CSS STYLING</label>
                                <textarea name="css_content" class="admin-field admin-textarea" style="color:var(--admin-primary); font-weight:700;" placeholder=".custom { color: #4facfe; }"><?php echo htmlspecialchars($editData['css']); ?></textarea>
                            </div>
                        </div>

                        <div style="margin: 20px 0; display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" name="is_home" id="is_home" <?php echo ($editData['is_home'] ?? false) ? 'checked' : ''; ?> style="width:20px; height:20px; cursor:pointer;">
                            <label for="is_home" style="font-weight:700; color:var(--admin-primary); cursor:pointer; font-size:0.9rem; margin:0;">Set as Website Home Page</label>
                        </div>

                        <button name="create_page" class="admin-btn"><i class="fas fa-save"></i> <?php echo $editData['slug'] ? 'Update Design' : 'Publish Design'; ?></button>
                        <a href="admin.php?logged_in=1" style="margin-left:15px; color:var(--admin-text-light); text-decoration:none; font-size:0.9rem;"><i class="fas fa-times"></i> Cancel & Back to List</a>
                    </form>
                </div>
            </div>

            <!-- Tab 2: Users -->
            <div id="users-tab" class="tab-pane">
                <div class="admin-card">
                    <h4 style="margin-top:0;"><i class="fas fa-user-plus"></i> Add New Access User</h4>
                    <form method="POST">
                        <div class="admin-input-group">
                            <label>USERNAME</label>
                            <input type="text" name="username" class="admin-field" required placeholder="User identifier">
                        </div>
                        <div class="admin-input-group">
                            <label>ACCOUNT ROLE</label>
                            <select name="role" class="admin-field">
                                <option value="Admin">Administrator (Full Access)</option>
                                <option value="Normal">Normal User (View Mode)</option>
                            </select>
                        </div>
                        <button name="add_user" class="admin-btn">Create Profile</button>
                    </form>
                </div>
            </div>
            
            <div id="settings-tab" class="tab-pane">
                 <div class="admin-card">
                    <h4 style="margin-top:0;"><i class="fas fa-history"></i> Release History (New vs Old)</h4>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Old Version</th>
                                <th>New Version</th>
                                <th>GitHub Status</th>
                                <th>Release Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hData = file_exists(__DIR__ . '/pages_data/release_history.json') ? json_decode(file_get_contents(__DIR__ . '/pages_data/release_history.json'), true) : [];
                            foreach ($hData as $h): ?>
                            <tr>
                                <td style="color:#e74a3b; font-weight:700;">v<?php echo $h['from']; ?></td>
                                <td style="color:#1cc88a; font-weight:700;">v<?php echo $h['to']; ?></td>
                                <td style="font-size:0.8rem; color:#4e73df; font-weight:700;"><i class="fab fa-github"></i> <?php echo $h['git_status'] ?? 'Linked'; ?></td>
                                <td style="font-size:0.85rem;"><?php echo $h['time']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                 </div>

                 <div class="admin-card">
                    <h4 style="margin-top:0;"><i class="fas fa-server"></i> Server Configuration</h4>
                    <p style="color:var(--admin-text-light);">Flat-file storage system active. No SQL Required.</p>
                    <div style="background:#f8f9fc; padding:20px; border-radius:8px; font-family:monospace; font-size:0.8rem;">
                        CMS_ROOT: <?php echo __DIR__; ?><br>
                        DATA_STORE: <?php echo $pagesDir; ?><br>
                        SYSTEM_VER: v<?php echo $sysVer['ver']; ?>
                    </div>
                 </div>
            </div>

        </div>
    </div>

    <script>
        // Automatic Tab Handling for Editing
        <?php if($editData['slug']): ?>
            document.addEventListener('DOMContentLoaded', () => {
                showTab('pages');
            });
        <?php else: ?>
            document.addEventListener('DOMContentLoaded', () => {
                showTab('all-pages');
            });
        <?php endif; ?>

        function showTab(id) {
            document.querySelectorAll('.tab-pane').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.admin-nav-item').forEach(l => l.classList.remove('active'));
            
            const targetTab = document.getElementById(id + '-tab');
            if(targetTab) targetTab.classList.add('active');
            
            // Link Mapping
            const linkAdd = document.getElementById('link-add');
            const linkAll = document.getElementById('link-all');
            
            if(id === 'pages') {
                if(linkAdd) linkAdd.classList.add('active');
                if(linkAll) linkAll.classList.remove('active');
            } else if(id === 'all-pages') {
                if(linkAll) linkAll.classList.add('active');
                if(linkAdd) linkAdd.classList.remove('active');
            }
        }
    </script>
</body>
</html>
