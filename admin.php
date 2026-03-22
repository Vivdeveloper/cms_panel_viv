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
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#1d2327">
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
$trashedPages = getTrashedCMSPages();
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

$validMainTabs = ['pages', 'trash', 'users', 'settings', 'contact', 'config'];
$mainTab       = isset($_GET['tab']) && in_array((string) $_GET['tab'], $validMainTabs, true) ? (string) $_GET['tab'] : 'pages';
$allUsers     = getAllUsers();
$userQuery    = isset($_GET['user']) ? (string) $_GET['user'] : '';
$editUserData = null;
if ($userQuery !== '' && $userQuery !== 'new') {
    $editUserData = cms_get_user($userQuery);
}
$userCompose = ($editUserData === null);
/** Mobile tab defaults (narrow screens): editor when a page/user is open in the URL. */
$mobilePeView = ($mainTab === 'pages' && isset($_GET['edit']) && $editData) ? 'editor' : 'list';
$mobileUeView = ($mainTab === 'users' && $userQuery !== '' && ($userQuery === 'new' || $editUserData !== null)) ? 'editor' : 'list';
$splitMobileStripClass = ($mainTab === 'pages') ? 'mobile-show-pages-tabs' : (($mainTab === 'users') ? 'mobile-show-users-tabs' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1d2327">
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
                <a href="admin.php" class="nav-btn <?php echo $mainTab === 'pages' ? 'current' : ''; ?>" onclick="switchMainTab('pages', event); return false;"><i class="fas fa-file-alt" aria-hidden="true"></i> Pages</a>
                <a href="admin.php?tab=trash" class="nav-btn <?php echo $mainTab === 'trash' ? 'current' : ''; ?>" onclick="switchMainTab('trash', event); return false;"><i class="fas fa-trash-alt" aria-hidden="true"></i> Trash</a>
                <a href="media_manager.php"><i class="fas fa-camera-retro" aria-hidden="true"></i> Media</a>
                <a href="backup.php"><i class="fas fa-cloud-download-alt" aria-hidden="true"></i> Backup</a>
                <a href="admin.php?tab=settings" class="nav-btn <?php echo $mainTab === 'settings' ? 'current' : ''; ?>" onclick="switchMainTab('settings', event); return false;"><i class="fas fa-cog" aria-hidden="true"></i> Site settings</a>
                <a href="admin.php?tab=contact" class="nav-btn <?php echo $mainTab === 'contact' ? 'current' : ''; ?>" onclick="switchMainTab('contact', event); return false;"><i class="fas fa-phone-alt" aria-hidden="true"></i> Call now</a>
                <a href="admin.php?tab=users" class="nav-btn <?php echo $mainTab === 'users' ? 'current' : ''; ?>" onclick="switchMainTab('users', event); return false;"><i class="fas fa-users-cog" aria-hidden="true"></i> User Roles</a>
                <a href="admin.php?tab=config" class="nav-btn <?php echo $mainTab === 'config' ? 'current' : ''; ?>" onclick="switchMainTab('config', event); return false;"><i class="fas fa-server" aria-hidden="true"></i> Server Config</a>
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
                    'last_admin'  => 'You must keep at least one Administrator. Add or promote another admin before changing this.',
                    'cannot_delete_admin' => 'The primary admin account (admin) cannot be deleted.',
                    'restore_slug_exists' => 'A live page already uses that URL slug. Rename or delete the existing page, then restore from Trash.',
                ];
                $adminToastStripKeys = ['saved', 'deleted', 'trashed', 'restored', 'permanently_deleted', 'settings_saved', 'contact_saved', 'pwd_ok', 'patched', 'user_updated', 'user_deleted'];
                $adminToastMessage = '';
                $adminToastByParam = [
                    'saved'           => 'Page saved.',
                    'deleted'         => 'Page removed.',
                    'trashed'         => 'Page moved to Trash.',
                    'restored'        => 'Page restored from Trash.',
                    'permanently_deleted' => 'Page deleted permanently.',
                    'settings_saved'  => 'Site settings saved.',
                    'contact_saved'   => 'Call now / contact saved. Numbers appear in the header and sticky bar.',
                    'pwd_ok'          => 'Admin password updated.',
                    'patched'         => 'Version bumped (patch).',
                    'user_updated'    => 'User role updated.',
                    'user_deleted'    => 'User removed.',
                ];
                foreach ($adminToastByParam as $param => $msg) {
                    if (!empty($_GET[$param])) {
                        $adminToastMessage = $msg;
                        break;
                    }
                }
                if (!empty($_GET['pwd_err'])): ?>
                <div class="notice notice-error admin-main-notice"><p style="margin:0;">Passwords must match and be at least 8 characters.</p></div>
                <?php endif;
                if ($errKey !== '' && isset($errMsgs[$errKey])): ?>
                <div class="notice notice-error admin-main-notice"><p style="margin:0;"><?php echo htmlspecialchars($errMsgs[$errKey]); ?></p></div>
                <?php endif;
                if ($adminToastMessage !== ''): ?>
                <noscript>
                    <div class="notice notice-success admin-main-notice"><p style="margin:0;"><?php echo htmlspecialchars($adminToastMessage); ?></p></div>
                </noscript>
                <?php endif; ?>
                <div class="wp-admin-toolbar">
                    <div class="wp-admin-toolbar-title">
                        <i class="fas fa-file-alt" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </div>
                </div>

            <div class="wp-split wp-split--mobile-tabbed<?php echo $splitMobileStripClass !== '' ? ' ' . htmlspecialchars($splitMobileStripClass) : ''; ?>" id="wp-split-main" data-pe-view="<?php echo htmlspecialchars($mobilePeView); ?>" data-ue-view="<?php echo htmlspecialchars($mobileUeView); ?>">
                <div class="mobile-pe-tabs mobile-pe-tabs--pages" role="tablist" aria-label="Page list and editor">
                    <button type="button" class="mobile-pe-tabs__btn<?php echo $mobilePeView === 'list' ? ' is-active' : ''; ?>" data-pe-tab="list" role="tab" aria-selected="<?php echo $mobilePeView === 'list' ? 'true' : 'false'; ?>">All pages</button>
                    <button type="button" class="mobile-pe-tabs__btn<?php echo $mobilePeView === 'editor' ? ' is-active' : ''; ?>" data-pe-tab="editor" role="tab" aria-selected="<?php echo $mobilePeView === 'editor' ? 'true' : 'false'; ?>">Edit page</button>
                </div>
                <div class="mobile-pe-tabs mobile-pe-tabs--users" role="tablist" aria-label="User list and editor">
                    <button type="button" class="mobile-pe-tabs__btn<?php echo $mobileUeView === 'list' ? ' is-active' : ''; ?>" data-ue-tab="list" role="tab" aria-selected="<?php echo $mobileUeView === 'list' ? 'true' : 'false'; ?>">All users</button>
                    <button type="button" class="mobile-pe-tabs__btn<?php echo $mobileUeView === 'editor' ? ' is-active' : ''; ?>" data-ue-tab="editor" role="tab" aria-selected="<?php echo $mobileUeView === 'editor' ? 'true' : 'false'; ?>">Edit user</button>
                </div>
                <!-- PAGES PANEL -->
                <div id="pages-panel" class="wp-panel <?php echo $mainTab === 'pages' ? 'active' : ''; ?>">
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
                                <form method="post" action="admin.php" style="display:inline;margin:0;" onclick="event.stopPropagation();" onsubmit="return confirm('Trash this page?');">
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

                <!-- TRASH PANEL -->
                <div id="trash-panel" class="wp-panel wp-panel-wide <?php echo $mainTab === 'trash' ? 'active' : ''; ?>">
                    <h1 class="wp-heading-inline">Trash</h1>
                    <hr class="wp-header-end">
                    <p class="description" style="margin:0 0 16px;font-size:13px;color:var(--ink3);line-height:1.5;">Trashed pages are hidden from the public site. <strong>Restore</strong> moves a page back, or <strong>Delete permanently</strong> removes its file (cannot be undone).</p>
                    <div class="pages-list">
                        <?php foreach ($trashedPages as $tp):
                            $tb = $tp['_trash_basename'] ?? '';
                            if ($tb === '') {
                                continue;
                            }
                            $slugRaw = (string) ($tp['slug'] ?? '');
                            $tslug = htmlspecialchars($slugRaw);
                            $ttitle = htmlspecialchars($tp['title'] ?? ucwords(str_replace('-', ' ', $slugRaw)));
                        ?>
                        <div class="pages-list-item" style="cursor:default;text-decoration:none;">
                            <div class="pages-list-title">
                                <?php echo $ttitle; ?>
                                <?php if (!empty($tp['is_home'])): ?><span class="status-badge">Was home</span><?php endif; ?>
                            </div>
                            <div class="pages-list-meta">
                                <span class="page-status-dot <?php echo ($tp['status'] ?? 'draft') === 'published' ? 'is-published' : 'is-draft'; ?>"></span>
                                <?php echo $tslug; ?>
                                &middot; <?php echo date('M d, Y', strtotime($tp['updated'] ?? 'now')); ?>
                            </div>
                            <div class="pages-list-actions">
                                <form method="post" style="display:inline;margin:0;" onclick="event.stopPropagation();">
                                    <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="trash_file" value="<?php echo htmlspecialchars($tb); ?>">
                                    <button type="submit" name="post_restore_page" value="1" class="linklike" style="background:none;border:none;padding:0;font:inherit;color:inherit;cursor:pointer;" aria-label="Restore page">Restore</button>
                                </form>
                                <form method="post" style="display:inline;margin:0;" onclick="event.stopPropagation();" onsubmit="return confirm('Delete this page permanently? This cannot be undone.');">
                                    <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="trash_file" value="<?php echo htmlspecialchars($tb); ?>">
                                    <button type="submit" name="post_permanent_delete_page" value="1" class="linklike" style="background:none;border:none;padding:0;font:inherit;color:#c53030;cursor:pointer;" aria-label="Delete page permanently">Delete permanently</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($trashedPages)): ?>
                        <div style="padding:20px;text-align:center;color:var(--mid);font-size:13px;">Trash is empty</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- USERS PANEL (list only — like All Pages) -->
                <div id="users-panel" class="wp-panel <?php echo $mainTab === 'users' ? 'active' : ''; ?>">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <span style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--mid);">All Users</span>
                        <a href="admin.php?tab=users&amp;user=new" class="button<?php echo ($mainTab === 'users' && $userCompose && ($userQuery === '' || $userQuery === 'new')) ? ' button-primary' : ''; ?>" style="height:26px;font-size:11px;padding:0 10px;">+ New</a>
                    </div>
                    <div class="pages-list">
                        <?php foreach ($allUsers as $u):
                            $uname = $u['username'] ?? '';
                            $norm  = cms_normalize_user_role($u['role'] ?? '');
                            $isActive = $editUserData && ($editUserData['username'] ?? '') === $uname;
                        ?>
                        <a href="admin.php?tab=users&amp;user=<?php echo urlencode($uname); ?>" class="pages-list-item <?php echo $isActive ? 'is-active' : ''; ?>" aria-label="Edit user <?php echo htmlspecialchars($uname); ?>">
                            <div class="pages-list-title">
                                <?php echo htmlspecialchars($uname); ?>
                                <?php if (strtolower($uname) === 'admin'): ?><span class="status-badge">Primary</span><?php endif; ?>
                            </div>
                            <div class="pages-list-meta">
                                <span class="page-status-dot <?php echo $norm === 'Administrator' ? 'is-published' : 'is-draft'; ?>"></span>
                                <?php echo htmlspecialchars($norm); ?>
                                <?php if (!empty($u['created'])): ?>&middot; <?php echo htmlspecialchars($u['created']); ?><?php endif; ?>
                            </div>
                            <div class="pages-list-actions">
                                <span class="user-list-edit-hint">Edit</span>
                                <?php if (strtolower($uname) !== 'admin'): ?>
                                <form method="post" action="admin.php" style="display:inline;margin:0;" onclick="event.stopPropagation();" onsubmit="return confirm('Remove user &quot;<?php echo htmlspecialchars($uname, ENT_QUOTES); ?>&quot;?');">
                                    <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="delete_username" value="<?php echo htmlspecialchars($uname); ?>">
                                    <button type="submit" name="delete_user" value="1" class="linklike" style="background:none;border:none;padding:0;font:inherit;color:inherit;cursor:pointer;" aria-label="Delete user">Delete</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php if (empty($allUsers)): ?>
                        <div style="padding:20px;text-align:center;color:var(--mid);font-size:13px;">No users yet</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SITE SETTINGS PANEL -->
                <div id="settings-panel" class="wp-panel wp-panel-wide <?php echo $mainTab === 'settings' ? 'active' : ''; ?>">
                    <h1 class="wp-heading-inline">Site settings</h1>
                    <hr class="wp-header-end">
                    <?php $st = getSiteSettings(); ?>
                    <div class="postbox" style="margin-bottom:16px;">
                        <h2 class="postbox-header">General</h2>
                        <div class="postbox-inner">
                        <form method="post">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                            <div class="form-group">
                                <label for="st-brand">Site name / brand</label>
                                <input type="text" id="st-brand" name="brand" class="wp-input" value="<?php echo htmlspecialchars($st['brand'] ?? ''); ?>">
                            </div>
                            <p class="description" style="margin:-8px 0 16px;font-size:12px;color:var(--ink3);">Phone and WhatsApp for the live site are edited under <strong>Call now</strong> in the sidebar.</p>
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
                                <label for="st-maint" style="margin:0;font-weight:600;">Maintenance mode (public pages show a hold message; admin tools still work. Use a private/incognito window to preview what visitors see.)</label>
                            </div>
                            <button type="submit" name="save_site_settings" class="button button-primary">Save site settings</button>
                        </form>
                        </div>
                    </div>
                </div>

                <!-- CALL NOW / CONTACT CTA PANEL -->
                <div id="contact-panel" class="wp-panel wp-panel-wide <?php echo $mainTab === 'contact' ? 'active' : ''; ?>">
                    <h1 class="wp-heading-inline">Call now &amp; WhatsApp</h1>
                    <hr class="wp-header-end">
                    <?php
                    $ct = getSiteSettings();
                    $ctSl = (($ct['sticky_cta_layout'] ?? 'full') === 'split') ? 'split' : 'full';
                    $ctaCallC1 = cms_sanitize_hex_color($ct['cta_call_color'] ?? '', '#1d4ed8');
                    $ctaCallC2 = cms_sanitize_hex_color($ct['cta_call_color2'] ?? '', '#1e40af');
                    $ctaCallLabel = cms_sanitize_cta_label($ct['cta_call_label'] ?? '', 'Call');
                    ?>
                    <div class="postbox" style="margin-bottom:16px;">
                        <h2 class="postbox-header">Public site buttons</h2>
                        <div class="postbox-inner">
                            <p class="description" style="margin:0 0 16px;font-size:13px;color:var(--ink3);line-height:1.5;">These numbers power the <strong>Call</strong> and green <strong>WhatsApp</strong> buttons in the <strong>desktop header</strong>, <strong>mobile menu</strong>, and <strong>bottom bar</strong> (same design everywhere). You can change the Call button gradient below.</p>
                            <form method="post" id="contact-cta-form">
                                <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                <div class="form-group">
                                    <span class="label-like" style="display:block;font-weight:600;margin-bottom:8px;font-size:13px;">Show buttons</span>
                                    <input type="hidden" name="cta_enable_call" value="0">
                                    <input type="hidden" name="cta_enable_whatsapp" value="0">
                                    <input type="hidden" name="cta_sticky_desktop" value="0">
                                    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;">
                                            <input type="checkbox" name="cta_enable_call" value="1" <?php echo !empty($ct['cta_enable_call']) ? 'checked' : ''; ?> style="width:18px;height:18px;">
                                            <span><strong>Enable Call</strong> button</span>
                                        </label>
                                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;">
                                            <input type="checkbox" name="cta_enable_whatsapp" value="1" <?php echo !empty($ct['cta_enable_whatsapp']) ? 'checked' : ''; ?> style="width:18px;height:18px;">
                                            <span><strong>Enable WhatsApp</strong> button</span>
                                        </label>
                                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;">
                                            <input type="checkbox" name="cta_sticky_desktop" value="1" <?php echo !empty($ct['cta_sticky_desktop']) ? 'checked' : ''; ?> style="width:18px;height:18px;">
                                            <span><strong>Bottom bar on desktop too</strong> (same split/full layout as mobile)</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="contact_phone">Phone (display &amp; tap-to-call)</label>
                                    <input type="text" id="contact_phone" name="contact_phone" class="wp-input" value="<?php echo htmlspecialchars($ct['phone'] ?? ''); ?>" placeholder="e.g. +91 99878 42957" autocomplete="tel">
                                    <p class="description" style="margin-top:6px;">Shown on buttons; digits are used for the <code>tel:</code> link.</p>
                                </div>
                                <div class="form-group">
                                    <label for="cta_call_label">Call button text</label>
                                    <input type="text" id="cta_call_label" name="cta_call_label" class="wp-input" maxlength="32" value="<?php echo htmlspecialchars($ctaCallLabel, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Call" autocomplete="off">
                                    <p class="description" style="margin-top:6px;">Label on the public Call button (header, mobile menu, bottom bar). Up to 32 characters.</p>
                                </div>
                                <div class="form-group">
                                    <label for="contact_whatsapp">WhatsApp number</label>
                                    <input type="text" id="contact_whatsapp" name="contact_whatsapp" class="wp-input" value="<?php echo htmlspecialchars($ct['whatsapp'] ?? ''); ?>" placeholder="Country code + number, no + required" inputmode="numeric" autocomplete="tel">
                                    <p class="description" style="margin-top:6px;">Use the same number you use in WhatsApp (with country code). Non-digits are stripped for the chat link.</p>
                                </div>
                                <div class="form-group">
                                    <span class="label-like" style="display:block;font-weight:600;margin-bottom:8px;font-size:13px;">Call button colors</span>
                                    <p class="description" style="margin:0 0 10px;">Vertical gradient (top → bottom), applied to every <strong>Call</strong> button on the public site.</p>
                                    <div class="cta-color-row" style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;">
                                        <label class="cta-color-picker-label" for="cta_call_color" style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer;">
                                            <span style="min-width:3.5rem;">Top</span>
                                            <input type="color" id="cta_call_color" name="cta_call_color" value="<?php echo htmlspecialchars($ctaCallC1); ?>" class="cta-color-input" title="Call button — top color">
                                        </label>
                                        <label class="cta-color-picker-label" for="cta_call_color2" style="display:flex;align-items:center;gap:10px;font-size:13px;cursor:pointer;">
                                            <span style="min-width:3.5rem;">Bottom</span>
                                            <input type="color" id="cta_call_color2" name="cta_call_color2" value="<?php echo htmlspecialchars($ctaCallC2); ?>" class="cta-color-input" title="Call button — bottom color">
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <span class="label-like" style="display:block;font-weight:600;margin-bottom:8px;font-size:13px;">Bottom bar layout</span>
                                    <p class="description" style="margin:0 0 10px;">Applies to the fixed bar on phones and tablets, and to the desktop bottom bar if you enabled it above. The desktop header uses the same split/full arrangement for the two buttons.</p>
                                    <div class="cta-layout-radios" style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:14px;">
                                        <label class="cta-layout-radio-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                                            <input type="radio" name="sticky_cta_layout" value="full" <?php echo $ctSl === 'full' ? 'checked' : ''; ?>>
                                            <span><strong>Full</strong> — stacked, each button full width</span>
                                        </label>
                                        <label class="cta-layout-radio-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                                            <input type="radio" name="sticky_cta_layout" value="split" <?php echo $ctSl === 'split' ? 'checked' : ''; ?>>
                                            <span><strong>Split</strong> — Call and WhatsApp side by side (50% / 50%)</span>
                                        </label>
                                    </div>
                                    <div class="cta-layout-preview-label" style="font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--ink2);margin-bottom:8px;">Live preview</div>
                                    <div id="cta-layout-preview-phone" class="cta-layout-preview-phone" aria-hidden="true" style="--cta-preview-call-a: <?php echo htmlspecialchars($ctaCallC1, ENT_QUOTES, 'UTF-8'); ?>; --cta-preview-call-b: <?php echo htmlspecialchars($ctaCallC2, ENT_QUOTES, 'UTF-8'); ?>;">
                                        <div class="cta-layout-preview-notch"></div>
                                        <div id="cta-layout-preview-inner" class="cta-layout-preview-inner cta-layout-preview-inner--<?php echo htmlspecialchars($ctSl); ?>">
                                            <span class="cta-pfake cta-pfake--call"><span id="cta-preview-call-label"><?php echo htmlspecialchars($ctaCallLabel, ENT_QUOTES, 'UTF-8'); ?></span></span>
                                            <span class="cta-pfake cta-pfake--wa">WhatsApp</span>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="save_contact_cta" class="button button-primary">Save contact buttons</button>
                            </form>
                        </div>
                    </div>
                    <div class="postbox">
                        <h2 class="postbox-header">Fallback (optional)</h2>
                        <div class="postbox-inner" style="font-size:12px;line-height:1.7;color:var(--ink3);">
                            If both fields are cleared, the site uses <code>CMS_PUBLIC_PHONE</code> and <code>CMS_PUBLIC_WHATSAPP</code> in <code>config.php</code>.
                        </div>
                    </div>
                </div>

                <!-- CONFIG PANEL -->
                <div id="config-panel" class="wp-panel wp-panel-wide <?php echo $mainTab === 'config' ? 'active' : ''; ?>">
                    <h1 class="wp-heading-inline">Server configuration</h1>
                    <hr class="wp-header-end">
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

                <!-- EDITOR (PAGES) -->
                <div class="wp-edit" id="editor-bar" style="display:<?php echo ($mainTab === 'pages') ? 'flex' : 'none'; ?>;">
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

                <!-- USER EDITOR (right pane — same pattern as page editor) -->
                <div class="wp-edit" id="user-edit-bar" style="display:<?php echo $mainTab === 'users' ? 'flex' : 'none'; ?>;">
                    <?php
                    $euname = $editUserData['username'] ?? '';
                    $enorm  = $editUserData ? cms_normalize_user_role($editUserData['role'] ?? '') : '';
                    ?>
                    <?php if ($userCompose): ?>
                    <div class="edit-header">
                        <h3 style="margin:0;font-size:14px;">Add new user</h3>
                        <button type="submit" form="user-add-form" name="add_user" value="1" class="button button-primary">Add user</button>
                    </div>
                    <div class="edit-body">
                        <form id="user-add-form" method="post">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                            <div class="form-group">
                                <label for="new-username">Username</label>
                                <input type="text" id="new-username" name="username" class="wp-input" required autocomplete="username" pattern="[a-zA-Z0-9._-]+" title="Letters, numbers, dot, underscore, hyphen">
                            </div>
                            <div class="form-group">
                                <label for="new-role">Role</label>
                                <select id="new-role" name="role" class="wp-input" required>
                                    <option value="Administrator">Administrator</option>
                                    <option value="Normal User">Normal User</option>
                                </select>
                            </div>
                        </form>
                        <hr style="margin:24px 0;border:0;border-top:1px solid var(--rule-l)">
                        <p style="font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;margin:0 0 12px;color:var(--ink2);">Change admin password</p>
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
                    <?php else: ?>
                    <div class="edit-header">
                        <h3 style="margin:0;font-size:14px;"><?php echo htmlspecialchars($euname); ?></h3>
                        <button type="submit" form="user-role-form" name="update_user_role" value="1" class="button button-primary">Save role</button>
                    </div>
                    <div class="edit-body">
                        <form id="user-role-form" method="post">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="edit_username" value="<?php echo htmlspecialchars($euname); ?>">
                            <div class="form-group">
                                <label for="edit-user-role">Role</label>
                                <select id="edit-user-role" name="role" class="wp-input">
                                    <option value="Administrator" <?php echo $enorm === 'Administrator' ? 'selected' : ''; ?>>Administrator</option>
                                    <option value="Normal User" <?php echo $enorm === 'Normal User' ? 'selected' : ''; ?>>Normal User</option>
                                </select>
                            </div>
                        </form>
                        <?php if (strtolower($euname) !== 'admin'): ?>
                        <form method="post" style="margin-top:16px;" onsubmit="return confirm('Remove user &quot;<?php echo htmlspecialchars($euname, ENT_QUOTES); ?>&quot;?');">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="delete_username" value="<?php echo htmlspecialchars($euname); ?>">
                            <button type="submit" name="delete_user" value="1" class="button button-danger">Delete user</button>
                        </form>
                        <?php endif; ?>
                        <hr style="margin:24px 0;border:0;border-top:1px solid var(--rule-l)">
                        <p style="font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;margin:0 0 12px;color:var(--ink2);">Change admin password</p>
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                            <div class="form-group">
                                <label for="np1b">New password</label>
                                <input type="password" id="np1b" name="new_admin_password" class="wp-input" minlength="8" required autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label for="np2b">Confirm password</label>
                                <input type="password" id="np2b" name="new_admin_password_confirm" class="wp-input" minlength="8" required autocomplete="new-password">
                            </div>
                            <button type="submit" name="change_admin_password" class="button button-primary">Update password</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var flash = <?php echo $adminToastMessage !== '' ? json_encode(['message' => $adminToastMessage, 'strip' => $adminToastStripKeys], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;
            if (!flash || !flash.message) return;
            try {
                var u = new URL(window.location.href);
                (flash.strip || []).forEach(function (k) { u.searchParams.delete(k); });
                var qs = u.searchParams.toString();
                window.history.replaceState({}, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
            } catch (e2) {}
            var el = document.createElement('div');
            el.className = 'admin-save-toast';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            el.appendChild(document.createTextNode(flash.message));
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'admin-save-toast__close';
            btn.setAttribute('aria-label', 'Dismiss');
            btn.textContent = '\u00D7';
            el.appendChild(btn);
            document.body.appendChild(el);
            requestAnimationFrame(function () { el.classList.add('admin-save-toast--show'); });
            var hideTimer;
            function dismiss() {
                clearTimeout(hideTimer);
                el.classList.remove('admin-save-toast--show');
                setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
            }
            hideTimer = setTimeout(dismiss, 5200);
            btn.addEventListener('click', dismiss);
        })();
        function syncMobileSplitStripClass(id) {
            var split = document.getElementById('wp-split-main');
            if (!split) return;
            split.classList.remove('mobile-show-pages-tabs', 'mobile-show-users-tabs');
            if (id === 'pages') {
                split.classList.add('mobile-show-pages-tabs');
            } else if (id === 'users') {
                split.classList.add('mobile-show-users-tabs');
            }
        }
        function syncMobilePeTabButtonsFromSplit(split) {
            if (!split) return;
            var v = split.getAttribute('data-pe-view') || 'list';
            document.querySelectorAll('.mobile-pe-tabs--pages [data-pe-tab]').forEach(function (b) {
                var on = b.getAttribute('data-pe-tab') === v;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        function syncMobileUeTabButtonsFromSplit(split) {
            if (!split) return;
            var v = split.getAttribute('data-ue-view') || 'list';
            document.querySelectorAll('.mobile-pe-tabs--users [data-ue-tab]').forEach(function (b) {
                var on = b.getAttribute('data-ue-tab') === v;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
        function applyEditorDisplayForMainTab(id) {
            var editor = document.getElementById('editor-bar');
            var userEditor = document.getElementById('user-edit-bar');
            var mq = window.matchMedia('(max-width:782px)');
            if (id === 'pages') {
                if (editor) {
                    if (mq.matches && document.getElementById('wp-split-main')) {
                        editor.style.removeProperty('display');
                    } else {
                        editor.style.display = 'flex';
                    }
                }
                if (userEditor) userEditor.style.display = 'none';
            } else if (id === 'users') {
                if (editor) editor.style.display = 'none';
                if (userEditor) {
                    if (mq.matches && document.getElementById('wp-split-main')) {
                        userEditor.style.removeProperty('display');
                    } else {
                        userEditor.style.display = 'flex';
                    }
                }
            } else {
                if (editor) editor.style.display = 'none';
                if (userEditor) userEditor.style.display = 'none';
            }
        }
        function switchMainTab(id, e) {
            e.preventDefault();
            document.querySelectorAll('.wp-panel').forEach(function(p) { p.classList.remove('active'); });
            document.querySelectorAll('#wp-admin-menu a').forEach(function(a) { a.classList.remove('current', 'active'); });

            var panel = document.getElementById(id + '-panel');
            if (panel) panel.classList.add('active');
            e.currentTarget.classList.add('current');

            syncMobileSplitStripClass(id);
            var qs = new URLSearchParams(window.location.search);
            if (id === 'pages') {
                var split = document.getElementById('wp-split-main');
                if (split) {
                    if (!qs.get('edit')) {
                        split.setAttribute('data-pe-view', 'list');
                    }
                    syncMobilePeTabButtonsFromSplit(split);
                }
            }
            if (id === 'users') {
                var splitU = document.getElementById('wp-split-main');
                if (splitU) {
                    var uParam = qs.get('user');
                    if (!uParam || String(uParam).trim() === '') {
                        splitU.setAttribute('data-ue-view', 'list');
                    }
                    syncMobileUeTabButtonsFromSplit(splitU);
                }
            }
            applyEditorDisplayForMainTab(id);
        }
        (function () {
            var split = document.getElementById('wp-split-main');
            if (!split) return;
            function bindTabRow(containerSel, attrName, splitDataKey) {
                document.querySelectorAll(containerSel + ' [' + attrName + ']').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var tab = btn.getAttribute(attrName);
                        if (!tab) return;
                        split.setAttribute(splitDataKey, tab);
                        document.querySelectorAll(containerSel + ' [' + attrName + ']').forEach(function (b) {
                            var on = b.getAttribute(attrName) === tab;
                            b.classList.toggle('is-active', on);
                            b.setAttribute('aria-selected', on ? 'true' : 'false');
                        });
                    });
                });
            }
            bindTabRow('.mobile-pe-tabs--pages', 'data-pe-tab', 'data-pe-view');
            bindTabRow('.mobile-pe-tabs--users', 'data-ue-tab', 'data-ue-view');
        })();
        (function () {
            var mqDesk = window.matchMedia('(min-width:783px)');
            function fixDesktopEditors() {
                if (!mqDesk.matches) return;
                var pp = document.getElementById('pages-panel');
                var up = document.getElementById('users-panel');
                if (pp && pp.classList.contains('active')) {
                    applyEditorDisplayForMainTab('pages');
                } else if (up && up.classList.contains('active')) {
                    applyEditorDisplayForMainTab('users');
                }
            }
            if (mqDesk.addEventListener) {
                mqDesk.addEventListener('change', fixDesktopEditors);
            } else if (mqDesk.addListener) {
                mqDesk.addListener(fixDesktopEditors);
            }
        })();
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
        (function () {
            document.addEventListener('keydown', function (e) {
                if (!(e.ctrlKey || e.metaKey) || String(e.key).toLowerCase() !== 's') return;
                e.preventDefault();
                function isShown(el) {
                    if (!el) return false;
                    return window.getComputedStyle(el).display !== 'none';
                }
                var st = document.getElementById('settings-panel');
                if (st && st.classList.contains('active')) {
                    var sb = st.querySelector('button[name="save_site_settings"]');
                    if (sb) { sb.click(); return; }
                }
                var ct = document.getElementById('contact-panel');
                if (ct && ct.classList.contains('active')) {
                    var cb = ct.querySelector('button[name="save_contact_cta"]');
                    if (cb) { cb.click(); return; }
                }
                var us = document.getElementById('users-panel');
                var ueb = document.getElementById('user-edit-bar');
                if (us && us.classList.contains('active') && isShown(ueb)) {
                    var ub = ueb.querySelector('.edit-header button.button-primary[type="submit"]');
                    if (ub) { ub.click(); return; }
                }
                var eb = document.getElementById('editor-bar');
                var pg = document.getElementById('pages-panel');
                if (pg && pg.classList.contains('active') && isShown(eb)) {
                    var pb = eb.querySelector('button[name="create_page"]');
                    if (pb) pb.click();
                }
            });
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
            if (tab === 'users' || tab === 'config' || tab === 'settings' || tab === 'contact' || tab === 'trash') {
                var nav = document.querySelector('#wp-admin-menu a[onclick*="' + tab + '"]');
                if (nav && typeof switchMainTab === 'function') {
                    switchMainTab(tab, { preventDefault: function () {}, currentTarget: nav });
                }
            }
        })();
        (function () {
            var inner = document.getElementById('cta-layout-preview-inner');
            var phoneWrap = document.getElementById('cta-layout-preview-phone');
            var c1 = document.getElementById('cta_call_color');
            var c2 = document.getElementById('cta_call_color2');
            var labelIn = document.getElementById('cta_call_label');
            var labelPrev = document.getElementById('cta-preview-call-label');
            function syncCallLabel() {
                if (!labelPrev || !labelIn) return;
                var t = (labelIn.value || '').trim();
                labelPrev.textContent = t || 'Call';
            }
            if (labelIn && labelPrev) {
                labelIn.addEventListener('input', syncCallLabel);
                labelIn.addEventListener('change', syncCallLabel);
            }
            if (!inner) return;
            function syncPreview() {
                var v = 'full';
                document.querySelectorAll('input[name="sticky_cta_layout"]').forEach(function (r) {
                    if (r.checked) v = r.value;
                });
                inner.className = 'cta-layout-preview-inner cta-layout-preview-inner--' + (v === 'split' ? 'split' : 'full');
            }
            document.querySelectorAll('input[name="sticky_cta_layout"]').forEach(function (r) {
                r.addEventListener('change', syncPreview);
            });
            function syncCallColors() {
                if (!phoneWrap || !c1 || !c2) return;
                phoneWrap.style.setProperty('--cta-preview-call-a', c1.value);
                phoneWrap.style.setProperty('--cta-preview-call-b', c2.value);
            }
            if (c1 && c2) {
                c1.addEventListener('input', syncCallColors);
                c1.addEventListener('change', syncCallColors);
                c2.addEventListener('input', syncCallColors);
                c2.addEventListener('change', syncCallColors);
            }
        })();
    </script>
</body>
</html>
