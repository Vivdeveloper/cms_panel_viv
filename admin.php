<?php
include 'config.php';
include 'cms_core.php';

// Handle Logout
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

// Access Control
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    if (isset($_POST['auth_key']) && cms_verify_admin_password($_POST['auth_key'])) {
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

$slugForForm = '';
$permalinkPreview = 'your-slug';
if ($editData) {
    $titleSlug = cms_sanitize_slug($editData['title'] ?? '');
    $slugForForm = ($titleSlug !== '') ? $titleSlug : ($editData['slug'] ?? '');
    $permalinkPreview = $slugForForm !== '' ? $slugForForm : ($editData['slug'] ?? '');
}

$csrf = cms_csrf_token();
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
                <?php
                $errKey = isset($_GET['err']) ? (string) $_GET['err'] : '';
                $errMsgs = [
                    'slug_exists' => 'That URL slug is already in use. Choose a different slug.',
                    'slug_empty'  => 'Enter a URL slug (or use the title to generate one).',
                    'slug_rename' => 'Could not rename the page file. Check file permissions.',
                    'csrf'        => 'Security check failed. Reload the page and try again.',
                ];
                if (!empty($_GET['saved'])): ?>
                <div class="notice notice-success" style="margin:12px 20px 0;"><p style="margin:0;">Page saved.</p></div>
                <?php endif;
                if (!empty($_GET['deleted'])): ?>
                <div class="notice notice-success" style="margin:12px 20px 0;"><p style="margin:0;">Page removed.</p></div>
                <?php endif;
                if (!empty($_GET['settings_saved'])): ?>
                <div class="notice notice-success" style="margin:12px 20px 0;"><p style="margin:0;">Site settings saved.</p></div>
                <?php endif;
                if (!empty($_GET['pwd_ok'])): ?>
                <div class="notice notice-success" style="margin:12px 20px 0;"><p style="margin:0;">Admin password updated.</p></div>
                <?php endif;
                if (!empty($_GET['pwd_err'])): ?>
                <div class="notice notice-error" style="margin:12px 20px 0;"><p style="margin:0;">Passwords must match and be at least 8 characters.</p></div>
                <?php endif;
                if (!empty($_GET['patched'])): ?>
                <div class="notice notice-success" style="margin:12px 20px 0;"><p style="margin:0;">Version bumped (patch).</p></div>
                <?php endif;
                if ($errKey !== '' && isset($errMsgs[$errKey])): ?>
                <div class="notice notice-error" style="margin:12px 20px 0;"><p style="margin:0;"><?php echo htmlspecialchars($errMsgs[$errKey]); ?></p></div>
                <?php endif; ?>
                <div class="wp-admin-toolbar">
                    <div class="wp-admin-toolbar-title">
                        <i class="fas fa-file-alt" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </div>
                </div>

            <div class="wp-split">
                <!-- PAGES PANEL -->
                <div id="pages-panel" class="wp-panel active">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--mid);">All Pages</span>
                        <a href="admin.php" class="button" style="height:26px;font-size:11px;padding:0 10px;">+ New</a>
                    </div>
                    <div class="pages-list">
                        <?php foreach ($allPages as $p): ?>
                        <a href="admin.php?edit=<?php echo $p['slug']; ?>" class="pages-list-item <?php echo (isset($_GET['edit']) && $_GET['edit'] === $p['slug']) ? 'is-active' : ''; ?>">
                            <div class="pages-list-title">
                                <?php echo htmlspecialchars($p['title'] ?? ucwords(str_replace('-', ' ', $p['slug']))); ?>
                                <?php if($p['is_home'] ?? false): ?><span class="status-badge">Home</span><?php endif; ?>
                            </div>
                            <div class="pages-list-meta">
                                <span class="page-status-dot <?php echo ($p['status'] ?? 'draft') === 'published' ? 'is-published' : 'is-draft'; ?>"></span>
                                <?php echo ($p['status'] ?? 'draft') === 'published' ? 'Published' : 'Draft'; ?>
                                &middot; <?php echo date('M d, Y', strtotime($p['updated'] ?? 'now')); ?>
                            </div>
                            <div class="pages-list-actions">
                                <span onclick="event.preventDefault();event.stopPropagation();window.open('<?php echo htmlspecialchars(cms_page_url($p['slug']), ENT_QUOTES); ?>','_blank')">View</span>
                                <form method="post" style="display:inline;margin:0;" onclick="event.preventDefault();event.stopPropagation();" onsubmit="return confirm('Trash this page?');">
                                    <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="post_delete_page" value="1">
                                    <input type="hidden" name="delete_slug" value="<?php echo htmlspecialchars($p['slug']); ?>">
                                    <button type="submit" class="linklike" style="background:none;border:none;padding:0;font:inherit;color:inherit;cursor:pointer;" aria-label="Trash page">Trash</button>
                                </form>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php if (empty($allPages)): ?>
                        <div style="padding:20px;text-align:center;color:var(--mid);font-size:13px;">No pages yet</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- USERS PANEL -->
                <div id="users-panel" class="wp-panel">
                    <h1 class="wp-heading-inline">User Roles</h1>
                    <hr class="wp-header-end">
                    <div class="postbox" style="margin-bottom:20px;">
                        <h2 class="postbox-header">Add new user</h2>
                        <div class="postbox-inner">
                        <form method="POST">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
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
                    <?php $st = getSiteSettings(); ?>
                    <div class="postbox" style="margin-bottom:16px;">
                        <h2 class="postbox-header">Site settings</h2>
                        <div class="postbox-inner">
                        <form method="post">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                            <div class="form-group">
                                <label for="st-brand">Site name / brand</label>
                                <input type="text" id="st-brand" name="brand" class="wp-input" value="<?php echo htmlspecialchars($st['brand'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="st-phone">Phone (display)</label>
                                <input type="text" id="st-phone" name="phone" class="wp-input" value="<?php echo htmlspecialchars($st['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="st-wa">WhatsApp number</label>
                                <input type="text" id="st-wa" name="whatsapp" class="wp-input" value="<?php echo htmlspecialchars($st['whatsapp'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="st-repo">Repo label (panel)</label>
                                <input type="text" id="st-repo" name="repo" class="wp-input" value="<?php echo htmlspecialchars($st['repo'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="st-lang">HTML lang</label>
                                <input type="text" id="st-lang" name="default_lang" class="wp-input" value="<?php echo htmlspecialchars($st['default_lang'] ?? 'en'); ?>" maxlength="10">
                            </div>
                            <div class="form-group">
                                <label for="st-tagline">Site tagline (optional)</label>
                                <input type="text" id="st-tagline" name="site_tagline" class="wp-input" value="<?php echo htmlspecialchars($st['site_tagline'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="st-og">Default OG image URL (absolute)</label>
                                <input type="url" id="st-og" name="default_og_image" class="wp-input" value="<?php echo htmlspecialchars($st['default_og_image'] ?? ''); ?>" placeholder="https://...">
                            </div>
                            <div class="form-group">
                                <label for="st-robots">Extra robots.txt lines</label>
                                <textarea id="st-robots" name="robots_extra" class="wp-input" style="height:80px;"><?php echo htmlspecialchars($st['robots_extra'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="st-analytics">Analytics / head HTML (trusted admins only)</label>
                                <textarea id="st-analytics" name="analytics_head_html" class="wp-input" style="height:100px;" placeholder="&lt;script&gt;...&lt;/script&gt;"><?php echo htmlspecialchars($st['analytics_head_html'] ?? ''); ?></textarea>
                            </div>
                            <input type="hidden" name="maintenance_mode" value="0">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                                <input type="checkbox" name="maintenance_mode" id="st-maint" value="1" <?php echo !empty($st['maintenance_mode']) ? 'checked' : ''; ?> style="width:18px;height:18px;">
                                <label for="st-maint" style="margin:0;font-weight:600;">Maintenance mode (visitors see a hold message; admins logged in still see the site)</label>
                            </div>
                            <button type="submit" name="save_site_settings" class="button button-primary">Save site settings</button>
                        </form>
                        </div>
                    </div>
                    <div class="postbox" style="margin-bottom:16px;">
                        <h2 class="postbox-header">Change admin password</h2>
                        <div class="postbox-inner">
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                            <div class="form-group">
                                <label for="np1">New password</label>
                                <input type="password" id="np1" name="new_admin_password" class="wp-input" minlength="8" required autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label for="np2">Confirm password</label>
                                <input type="password" id="np2" name="new_admin_password_confirm" class="wp-input" minlength="8" required autocomplete="new-password">
                            </div>
                            <button type="submit" name="change_admin_password" class="button button-primary">Update password</button>
                        </form>
                        </div>
                    </div>
                    <div class="postbox">
                        <h2 class="postbox-header">System info</h2>
                        <div class="postbox-inner" style="font-family:var(--m); font-size:12px; line-height:2; color:var(--ink3); word-break:break-all;">
                        [CORE] INSTANCE_VER: v<?php echo htmlspecialchars($sysVer['ver']); ?><br>
                        [CORE] DATA_PATH: <?php echo htmlspecialchars(__DIR__); ?>/pages_data/<br>
                        [CORE] UPLOAD_PATH: <?php echo htmlspecialchars(__DIR__); ?>/uploads/<br>
                        [SEO] SITEMAP: <?php echo htmlspecialchars(cms_url('sitemap.php')); ?><br>
                        [SEO] ROBOTS: <?php echo htmlspecialchars(cms_url('robots.php')); ?><br>
                        [SECURITY] CSRF + hashed admin password<br>
                        </div>
                    </div>
                    <div class="notice notice-error" style="margin-top:8px;">
                        <p style="margin:0 0 10px;"><strong>Maintenance:</strong> force version patch</p>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                            <button type="submit" name="force_patch_release" class="button button-danger" onclick="return confirm('Bump patch version in system_version.json?');">Force patch release</button>
                        </form>
                        <p class="description" style="margin-top:10px; margin-bottom:0;">Use only when local mirror version mismatches production.</p>
                    </div>
                </div>

                <!-- EDITOR (FOR PAGES ONLY) -->
                <div class="wp-edit" id="editor-bar">
                    <form id="page-editor-form" action="admin.php" method="POST" style="display:contents;" data-is-new="<?php echo $editData ? '0' : '1'; ?>">
                        <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="current_slug" value="<?php echo $editData ? htmlspecialchars($editData['slug']) : ''; ?>">
                        <div class="edit-header">
                            <h3 style="margin:0; font-size:14px;"><?php echo $editData ? 'Editing Design' : 'Compose New'; ?></h3>
                            <button type="submit" name="create_page" class="button button-primary">Save</button>
                        </div>
                        <div class="edit-body">
                            <div class="form-group">
                                <label for="page_title">TITLE</label>
                                <input type="text" name="page_title" id="page_title" class="wp-input" placeholder="Page title" value="<?php echo $editData ? htmlspecialchars($editData['title'] ?? ucwords(str_replace('-', ' ', $editData['slug']))) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="page_slug">SLUG <span class="description" style="display:inline;font-weight:400;margin-left:4px;">(URL — edit anytime; Save applies)</span></label>
                                <input type="text" name="slug" id="page_slug" class="wp-input" autocomplete="off" value="<?php echo $editData ? htmlspecialchars($slugForForm) : ''; ?>" placeholder="e.g. my-page" pattern="[a-z0-9\-]*" title="Lowercase letters, numbers, and hyphens only">
                                <p class="description" style="margin-top:8px;margin-bottom:0;display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                                    <span>Permalink: <code id="permalink_preview" style="font-size:12px;word-break:break-all;"><?php echo htmlspecialchars($editData ? cms_page_url($permalinkPreview) : cms_page_url('your-slug')); ?></code></span>
                                    <button type="button" class="button" id="slug_from_title" style="height:26px;font-size:12px;padding:0 10px;">Regenerate from title</button>
                                </p>
                            </div>
                            <div class="form-group">
                                <label for="meta_description">Meta description (SEO)</label>
                                <textarea id="meta_description" name="meta_description" class="wp-input" style="height:72px;" maxlength="320" placeholder="Short summary for search results"><?php echo $editData ? htmlspecialchars($editData['meta_description'] ?? '') : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="og_image">Open Graph image URL (optional)</label>
                                <input type="url" id="og_image" name="og_image" class="wp-input" value="<?php echo $editData ? htmlspecialchars($editData['og_image'] ?? '') : ''; ?>" placeholder="https://...">
                            </div>
                            <div class="form-group">
                                <label>HTML MODULES</label>
                                <textarea name="html_content" class="wp-code wp-input"><?php echo $editData ? htmlspecialchars($editData['html']) : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>STYLING (CSS)</label>
                                <textarea name="css_content" class="wp-code wp-input" style="height:150px;"><?php echo $editData ? htmlspecialchars($editData['css']) : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>STATUS</label>
                                <select name="page_status" class="wp-input">
                                    <option value="draft" <?php echo ($editData && ($editData['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo ($editData && ($editData['status'] ?? 'draft') === 'published') ? 'selected' : ''; ?>>Published</option>
                                </select>
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
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    var btn = document.querySelector('#editor-bar form button[name="create_page"]');
                    if (btn && document.getElementById('editor-bar').style.display !== 'none') btn.click();
                }
            });
            var mq = window.matchMedia('(min-width: 783px)');
            function closeIfDesktop() { if (mq.matches) { setOpen(false); } }
            if (mq.addEventListener) { mq.addEventListener('change', closeIfDesktop); }
            else if (mq.addListener) { mq.addListener(closeIfDesktop); }
        })();
        (function () {
            var form = document.getElementById('page-editor-form');
            if (!form) return;

            var isNew = form.getAttribute('data-is-new') === '1';
            var titleEl = document.getElementById('page_title');
            var slugEl = document.getElementById('page_slug');
            var permEl = document.getElementById('permalink_preview');
            var slugTouched = false;
            var publicBase = <?php echo json_encode(rtrim(cms_site_url(), '/') . '/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            function clientSlugify(s) {
                if (!s) return '';
                s = s.toLowerCase().trim().replace(/\s+/g, '-').replace(/[^a-z0-9\-]/g, '').replace(/-+/g, '-');
                return s.replace(/^-+|-+$/g, '');
            }
            function updatePermalink() {
                if (!permEl || !slugEl || !titleEl) return;
                var fromName = clientSlugify(titleEl.value);
                var seg;
                if (slugTouched) {
                    seg = (slugEl.value || '').trim();
                } else {
                    seg = fromName || (slugEl.value || '').trim();
                }
                if (!seg) {
                    permEl.textContent = publicBase + 'your-slug';
                    return;
                }
                permEl.textContent = publicBase + encodeURIComponent(seg);
            }

            function snapshotForm() {
                var fd = new FormData(form);
                var parts = [];
                fd.forEach(function (v, k) {
                    parts.push(k + '=' + encodeURIComponent(String(v)));
                });
                parts.sort();
                return parts.join('&');
            }
            var initialSnap;
            var dirty = false;
            function checkDirty() {
                dirty = snapshotForm() !== initialSnap;
            }

            if (titleEl && slugEl) {
                titleEl.addEventListener('input', function () {
                    if (!slugTouched) {
                        slugEl.value = clientSlugify(titleEl.value);
                    }
                    updatePermalink();
                    checkDirty();
                });
                slugEl.addEventListener('input', function () {
                    slugTouched = true;
                    updatePermalink();
                    checkDirty();
                });
            }
            var regBtn = document.getElementById('slug_from_title');
            if (regBtn && titleEl && slugEl) {
                regBtn.addEventListener('click', function () {
                    slugEl.value = clientSlugify(titleEl.value);
                    slugTouched = false;
                    updatePermalink();
                    checkDirty();
                });
            }
            updatePermalink();
            initialSnap = snapshotForm();

            form.addEventListener('input', function (e) {
                if (e.target === titleEl || e.target === slugEl) return;
                checkDirty();
            });
            form.addEventListener('change', checkDirty);
            form.addEventListener('submit', function () {
                dirty = false;
            });
            window.addEventListener('beforeunload', function (e) {
                if (dirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            function willLeaveEditing(href) {
                if (!href) return false;
                var h = href.trim();
                if (h === '#' || /^javascript:/i.test(h)) return false;
                if (h.charAt(0) === '#' && h.indexOf('?') === -1) return false;
                if (/admin\.php(\?|$)/i.test(h)) return true;
                if (/media_manager\.php/i.test(h)) return true;
                if (/backup\.php/i.test(h)) return true;
                if (/[?&]logout=/i.test(h)) return true;
                return false;
            }
            document.addEventListener('click', function (e) {
                if (!dirty) return;
                var a = e.target.closest && e.target.closest('a[href]');
                if (!a) return;
                if (a.getAttribute('target') === '_blank') return;
                var href = a.getAttribute('href');
                if (!willLeaveEditing(href)) return;
                if (!window.confirm('You have unsaved changes. Leave without saving?')) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);
        })();
        (function () {
            var params = new URLSearchParams(window.location.search);
            var tab = params.get('tab');
            if (tab === 'users' || tab === 'config') {
                var nav = document.querySelector('#wp-admin-menu a[onclick*="' + tab + '"]');
                if (nav && typeof switchMainTab === 'function') {
                    switchMainTab(tab, { preventDefault: function () {}, currentTarget: nav });
                }
            }
        })();
    </script>
</body>
</html>
