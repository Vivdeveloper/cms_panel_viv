<?php
include 'config.php';
include 'cms_core.php';

// Handle Logout
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

// Access Control
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    if (isset($_POST['auth_key']) && $_POST['auth_key'] === '12345') {
        $_SESSION['is_admin'] = true;
        header("Location: admin.php"); exit;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Log In — CMS</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap">
        <link rel="stylesheet" href="<?php echo cms_url('admin_style.css'); ?>">
    </head>
    <body class="wp-login-body">
        <div class="wp-login-card">
            <h1>SEO Website Designer</h1>
            <form method="POST">
                <input type="password" name="auth_key" placeholder="Password" required autocomplete="current-password" autofocus>
                <button type="submit">Log In</button>
            </form>
        </div>
    </body>
    </html>
    <?php exit;
}

$sysVer = getSystemVersion();
$allPages = getAllCMSPages();
$editData = null;
if (isset($_GET['edit'])) { $editData = getCMSPage($_GET['edit']); }

// User Management (New)
if (isset($_POST['add_user'])) {
    checkAdmin();
    createUser($_POST['username'], $_POST['role']);
    header("Location: admin.php?tab=users"); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pages — CMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="<?php echo cms_url('admin_style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="wp-admin-skin wp-admin-dashboard">
    <div class="wp-admin-shell">
        <header class="wp-admin-bar" role="banner">
            <div class="wp-admin-bar-row">
                <div class="wp-admin-bar-site">
                    <button type="button" class="wp-menu-toggle" id="wp-menu-toggle" aria-expanded="false" aria-controls="wp-admin-menu" aria-label="Open menu">
                        <span class="screen-reader-text">Menu</span>
                        <i class="fas fa-bars" aria-hidden="true"></i>
                    </button>
                    <div class="wp-admin-bar-brand">
                        <span class="wp-brand-mark" aria-hidden="true">S</span>
                        <div class="wp-brand-text">
                            <span class="wp-brand-name">SEO Website Designer</span>
                        </div>
                    </div>
                </div>
                <div class="wp-admin-bar-secondary">
                    <a href="index.php" target="_blank" rel="noopener" class="wp-bar-visit"><i class="fas fa-external-link-alt" aria-hidden="true"></i><span>View site</span></a>
                    <span class="wp-bar-user">
                        <span class="wp-bar-avatar" aria-hidden="true">A</span>
                        <span class="wp-bar-greet">Howdy, <strong>admin</strong></span>
                    </span>
                </div>
            </div>
        </header>

        <div class="wp-admin-frame">
            <div class="wp-admin-menu-backdrop" aria-hidden="true"></div>
            <nav class="wp-admin-menu" id="wp-admin-menu" aria-label="Main menu">
                <div class="menu-top">Navigation</div>
                <a href="#" class="nav-btn current" onclick="switchMainTab('pages', event); return false;"><i class="fas fa-file-alt" aria-hidden="true"></i> Pages</a>
                <a href="media_manager.php"><i class="fas fa-camera-retro" aria-hidden="true"></i> Media</a>
                <a href="backup.php"><i class="fas fa-cloud-download-alt" aria-hidden="true"></i> Backup</a>
                <a href="#" class="nav-btn" onclick="switchMainTab('users', event); return false;"><i class="fas fa-users-cog" aria-hidden="true"></i> User Roles</a>
                <a href="#" class="nav-btn" onclick="switchMainTab('config', event); return false;"><i class="fas fa-server" aria-hidden="true"></i> Server Config</a>
                <div class="menu-footer">
                    <a href="?logout=1"><i class="fas fa-power-off" aria-hidden="true"></i> Log Out</a>
                </div>
            </nav>

            <div class="wp-admin-main">
                <div class="wp-admin-toolbar">
                    <div class="wp-admin-toolbar-title">
                        <i class="fas fa-file-alt" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </div>
                </div>

            <div class="wp-split">
                <!-- PAGES PANEL -->
                <div id="pages-panel" class="wp-panel active">
                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:18px;">
                        <h1 class="wp-heading-inline" style="margin:0;">All Pages</h1>
                        <a href="admin.php" class="button">+ Add New</a>
                    </div>
                    <table class="page-table">
                        <thead><tr><th>Title</th><th style="width:120px;">Date Updated</th></tr></thead>
                        <tbody>
                            <?php foreach ($allPages as $p): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center;">
                                        <a href="admin.php?edit=<?php echo $p['slug']; ?>" style="text-decoration:none; color:var(--ink); font-weight:600; font-size:14px;"><?php echo ucwords(str_replace('-', ' ', $p['slug'])); ?></a>
                                        <?php if($p['is_home'] ?? false): ?><span class="status-badge">Front Page</span><?php endif; ?>
                                    </div>
                                    <div class="row-actions">
                                        <a href="admin.php?edit=<?php echo $p['slug']; ?>">Edit</a> | 
                                        <a href="view.php?page=<?php echo $p['slug']; ?>" target="_blank">View</a> | 
                                        <a href="admin.php?delete=<?php echo $p['slug']; ?>" class="delete" onclick="return confirm('Trash this page?')">Trash</a>
                                    </div>
                                </td>
                                <td style="font-size: 12px; color: var(--mid);"><?php echo date('Y/m/d', strtotime($p['updated'] ?? 'now')); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- USERS PANEL -->
                <div id="users-panel" class="wp-panel">
                    <h1 class="wp-heading-inline">User Roles</h1>
                    <hr class="wp-header-end">
                    <div class="postbox" style="margin-bottom:20px;">
                        <h2 class="postbox-header">Add new user</h2>
                        <div class="postbox-inner">
                        <form method="POST">
                            <div class="form-group">
                                <label for="new-username">Username</label>
                                <input type="text" id="new-username" name="username" class="wp-input" required>
                            </div>
                            <div class="form-group">
                                <label for="new-role">Role</label>
                                <select id="new-role" name="role" class="wp-input">
                                    <option>Administrator</option>
                                    <option>Normal User</option>
                                </select>
                            </div>
                            <button type="submit" name="add_user" class="button button-primary">Add user</button>
                        </form>
                        </div>
                    </div>
                    <table class="page-table">
                        <thead><tr><th>User Identifier</th><th>Role</th></tr></thead>
                        <tbody>
                            <?php foreach (getAllUsers() as $u): ?>
                            <tr>
                                <td style="font-weight:700; color:var(--ink);"><?php echo $u['username']; ?></td>
                                <td style="font-size:12px;"><?php echo $u['role']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- CONFIG PANEL -->
                <div id="config-panel" class="wp-panel">
                    <h1 class="wp-heading-inline">Server configuration</h1>
                    <hr class="wp-header-end">
                    <div class="postbox">
                        <h2 class="postbox-header">System info</h2>
                        <div class="postbox-inner" style="font-family:var(--mono); font-size:12px; line-height:2; color:var(--ink-3);">
                        [CORE] INSTANCE_VER: v<?php echo htmlspecialchars($sysVer['ver']); ?><br>
                        [CORE] DATA_PATH: <?php echo htmlspecialchars(__DIR__); ?>/pages_data/<br>
                        [CORE] UPLOAD_PATH: <?php echo htmlspecialchars(__DIR__); ?>/uploads/<br>
                        [SECURITY] SESSION_PROTECTION: ACTIVE<br>
                        [SYSTEM] OS: Mac/Local Mirror
                        </div>
                    </div>
                    <div class="notice notice-error" style="margin-top:8px;">
                        <p style="margin:0 0 10px;"><strong>Maintenance:</strong> version override</p>
                        <button type="button" class="button button-danger">Force patch release</button>
                        <p class="description" style="margin-top:10px; margin-bottom:0;">Use only when local mirror version mismatches production.</p>
                    </div>
                </div>

                <!-- EDITOR (FOR PAGES ONLY) -->
                <div class="wp-edit" id="editor-bar">
                    <form action="admin.php" method="POST" style="display:contents;">
                        <input type="hidden" name="current_slug" value="<?php echo $editData ? $editData['slug'] : ''; ?>">
                        <div class="edit-header">
                            <h3 style="margin:0; font-size:14px;"><?php echo $editData ? 'Editing Design' : 'Compose New'; ?></h3>
                            <button type="submit" name="create_page" class="button button-primary">Save</button>
                        </div>
                        <div class="edit-body">
                            <div class="form-group">
                                <label>URL DESTINATION</label>
                                <input type="text" name="slug" class="wp-input" value="<?php echo $editData ? $editData['slug'] : ''; ?>" <?php echo $editData ? 'readonly' : 'required'; ?>>
                            </div>
                            <div class="form-group">
                                <label>HTML MODULES</label>
                                <textarea name="html_content" class="wp-code wp-input"><?php echo $editData ? htmlspecialchars($editData['html']) : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>STYLING (CSS)</label>
                                <textarea name="css_content" class="wp-code wp-input" style="height:150px;"><?php echo $editData ? htmlspecialchars($editData['css']) : ''; ?></textarea>
                            </div>
                            <div style="display:flex; align-items:center; gap:10px; background:var(--wh); padding:10px 12px; border:1px solid var(--rule);">
                                <input type="checkbox" name="is_home" id="is_home" <?php echo ($editData && ($editData['is_home'] ?? false)) ? 'checked' : ''; ?> style="width:18px; height:18px;">
                                <label for="is_home" style="margin:0; font-weight:600; font-size:13px;">Set as front page</label>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            </div>
        </div>
    </div>
    <script>
        function switchMainTab(id, e) {
            e.preventDefault();
            document.querySelectorAll('.wp-panel').forEach(function(p) { p.classList.remove('active'); });
            document.querySelectorAll('#wp-admin-menu a').forEach(function(a) { a.classList.remove('current', 'active'); });

            var panel = document.getElementById(id + '-panel');
            if (panel) panel.classList.add('active');
            e.currentTarget.classList.add('current');

            var editor = document.getElementById('editor-bar');
            if (editor) editor.style.display = id === 'pages' ? 'flex' : 'none';
        }
        (function () {
            var shell = document.querySelector('.wp-admin-shell');
            var toggle = document.getElementById('wp-menu-toggle');
            var backdrop = document.querySelector('.wp-admin-menu-backdrop');
            if (!shell || !toggle) return;
            function setOpen(open) {
                shell.classList.toggle('wp-menu-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            toggle.addEventListener('click', function () {
                setOpen(!shell.classList.contains('wp-menu-open'));
            });
            if (backdrop) {
                backdrop.addEventListener('click', function () { setOpen(false); });
            }
            document.querySelectorAll('#wp-admin-menu a[href]').forEach(function (a) {
                var h = a.getAttribute('href');
                if (h && h !== '#' && h.indexOf('#') !== 0) {
                    a.addEventListener('click', function () { setOpen(false); });
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { setOpen(false); }
            });
            var mq = window.matchMedia('(min-width: 783px)');
            function closeIfDesktop() { if (mq.matches) { setOpen(false); } }
            if (mq.addEventListener) { mq.addEventListener('change', closeIfDesktop); }
            else if (mq.addListener) { mq.addListener(closeIfDesktop); }
        })();
    </script>
</body>
</html>
