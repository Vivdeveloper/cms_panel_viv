<?php
include 'config.php';
include 'cms_core.php';
require_once __DIR__ . '/admin_menu.php';

// Handle Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Access Control (email or username in users_data + shared site password)
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    $cmsLoginError = '';
    $cmsLoginValue = '';
    if (isset($_POST['auth_key'])) {
        $ident = trim((string) ($_POST['cms_login'] ?? $_POST['cms_username'] ?? ''));
        $cmsLoginValue = $ident;
        if (cms_login_is_locked_out()) {
            $cmsLoginError = 'Too many failed attempts. Try again in 15 minutes.';
        } elseif ($ident === '') {
            $cmsLoginError = 'Enter your email or username.';
        } elseif (($row = cms_find_user_for_login($ident)) === null) {
            cms_login_attempts_record();
            $cmsLoginError = 'No account found for that email or username.';
        } elseif (!cms_verify_admin_password((string) ($_POST['auth_key'] ?? ''))) {
            cms_login_attempts_record();
            $cmsLoginError = 'Wrong password.';
        } else {
            $resolved = (string) ($row['username'] ?? '');
            if ($resolved === '') {
                $cmsLoginError = 'Account error. Contact an administrator.';
            } else {
                session_regenerate_id(true);
                cms_login_attempts_reset();
                $_SESSION['is_admin'] = true;
                $_SESSION['cms_username'] = $resolved;
                header('Location: admin.php');
                exit;
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#0038ff">
        <title>Sign in — SEO Website Designer</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
        <link rel="stylesheet" href="<?php echo cms_url('admin_style.css'); ?>">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body class="wp-login-body cms-login-page">
        <div class="cms-login-bg" aria-hidden="true"></div>
        <main class="cms-login-shell">
            <div class="cms-login-card">
                <div class="cms-login-card__brand">
                    <span class="cms-login-card__mark" aria-hidden="true">S</span>
                    <div class="cms-login-card__brand-text">
                        <span class="cms-login-card__brand-name">SEO Website Designer</span>
                        <span class="cms-login-card__brand-tag">CMS</span>
                    </div>
                </div>
                <h1 class="cms-login-card__title">Sign in</h1>
                <?php if ($cmsLoginError !== ''): ?>
                <div class="cms-login-alert" role="alert"><?php echo htmlspecialchars($cmsLoginError); ?></div>
                <?php endif; ?>
                <form method="post" class="cms-login-form">
                    <div class="cms-login-field">
                        <label class="cms-login-label" for="cms_login">Email or username</label>
                        <input type="text" id="cms_login" name="cms_login" class="cms-login-input" placeholder="you@example.com or jane.doe" required autocomplete="username" value="<?php echo htmlspecialchars($cmsLoginValue); ?>">
                    </div>
                    <div class="cms-login-field cms-login-field--password">
                        <label class="cms-login-label" for="auth_key">Password</label>
                        <div class="cms-login-pass">
                            <input type="password" id="auth_key" name="auth_key" class="cms-login-input cms-login-input--pass" placeholder="Enter password" required autocomplete="current-password">
                            <button type="button" class="cms-login-pass__toggle" id="cms-login-pass-toggle" aria-label="Show password" aria-pressed="false" title="Show password">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <?php
                        $forgotHref = cms_whatsapp_password_reset_url();
                        if ($forgotHref === '') {
                            $forgotTel = cms_phone_tel_digits();
                            $forgotHref = $forgotTel !== '' ? 'tel:' . preg_replace('/\s+/', '', $forgotTel) : '';
                        }
                        if ($forgotHref !== ''):
                        ?>
                        <div class="cms-login-forgot-row">
                            <a class="cms-login-forgot-link" href="<?php echo htmlspecialchars($forgotHref); ?>"<?php echo (strpos($forgotHref, 'http') === 0) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>Forgot password?</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="cms-login-submit">Log in</button>
                </form>
            </div>
        </main>
        <script>
        (function () {
            var input = document.getElementById('auth_key');
            var btn = document.getElementById('cms-login-pass-toggle');
            if (!input || !btn) return;
            var icon = btn.querySelector('i');
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                btn.setAttribute('title', show ? 'Hide password' : 'Show password');
                if (icon) {
                    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
                }
            });
        })();
        </script>
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

$validMainTabs = ['pages', 'trash', 'users', 'settings', 'html_tags', 'contact', 'contact_form', 'crm', 'config'];
$mainTab       = isset($_GET['tab']) && in_array((string) $_GET['tab'], $validMainTabs, true) ? (string) $_GET['tab'] : 'pages';
$menuUserRecord = cms_current_user_record();
$allowedMenuKeys = cms_user_allowed_menu_keys($menuUserRecord);
$cmsPagesReadOnly = !cms_user_can_edit_pages();
$pageEditorReadonly = $cmsPagesReadOnly && $editData;
$pageEditorEmptyReadonly = $cmsPagesReadOnly && !$editData;
if (!in_array($mainTab, $allowedMenuKeys, true)) {
    $fallbackTab = $allowedMenuKeys[0] ?? 'pages';
    header('Location: admin.php?tab=' . rawurlencode($fallbackTab));
    exit;
}
$allUsers     = getAllUsers();
$userQuery    = isset($_GET['user']) ? (string) $_GET['user'] : '';
$editUserData = null;
if ($userQuery !== '' && $userQuery !== 'new') {
    $editUserData = cms_get_user($userQuery);
}
$userCompose = ($editUserData === null);
$userNavItemsMenu = cms_admin_nav_items();
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
<body class="wp-admin-skin wp-admin-dashboard<?php echo cms_is_maintenance_mode() ? ' admin-public-maintenance' : ''; ?>">
    <div class="wp-admin-shell">
        <header class="wp-admin-bar" role="banner">
            <div class="wp-admin-bar-row">
                <div class="wp-admin-bar-site">
                    <button type="button" class="wp-menu-toggle" id="wp-menu-toggle" aria-expanded="false" aria-controls="wp-admin-menu" aria-label="Open menu">
                        <span class="screen-reader-text">Menu</span>
                        <i class="fas fa-bars wp-menu-toggle__icon wp-menu-toggle__icon--bars" aria-hidden="true"></i>
                        <i class="fas fa-times wp-menu-toggle__icon wp-menu-toggle__icon--close" aria-hidden="true"></i>
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
                        <?php
                        $barUname = cms_session_username();
                        $barLabel = $barUname !== '' ? $barUname : 'admin';
                        $barLetter = strtoupper(substr($barLabel, 0, 1));
                        ?>
                        <span class="wp-bar-avatar" aria-hidden="true"><?php echo htmlspecialchars($barLetter); ?></span>
                        <span class="wp-bar-greet">Howdy, <strong><?php echo htmlspecialchars($barLabel); ?></strong></span>
                    </span>
                </div>
            </div>
        </header>

        <div class="wp-admin-frame">
            <?php cms_render_admin_sidebar_nav(['mode' => 'spa', 'main_tab' => $mainTab, 'allowed_keys' => $allowedMenuKeys]); ?>

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
                    'header_logo_upload' => 'Header logo was not updated (invalid type, too large, or server error). Other site settings were saved. Use JPG, PNG, GIF, WebP, or SVG under 3 MB, or clear the file field and save.',
                    'read_only' => 'Your role can view pages only. You cannot create, edit, or change the trash.',
                    'email_taken' => 'That email is already used by another user. Choose a different email.',
                    'email_invalid' => 'Enter a valid email address, or leave the email field empty.',
                ];
                $adminToastStripKeys = ['saved', 'deleted', 'trashed', 'restored', 'permanently_deleted', 'settings_saved', 'contact_saved', 'contact_form_saved', 'contact_form_fields_err', 'crm_updated', 'crm_locked', 'pwd_ok', 'patched', 'user_updated', 'user_deleted'];
                $adminToastMessage = '';
                $adminToastByParam = [
                    'saved'           => 'Page saved.',
                    'deleted'         => 'Page removed.',
                    'trashed'         => 'Page moved to Trash.',
                    'restored'        => 'Page restored from Trash.',
                    'permanently_deleted' => 'Page deleted permanently.',
                    'settings_saved'  => 'Site settings saved.',
                    'contact_saved'   => 'Call & WhatsApp settings saved.',
                    'contact_form_saved' => 'Contact form & email settings saved.',
                    'crm_updated' => 'Lead status saved.',
                    'pwd_ok'          => 'Admin password updated.',
                    'patched'         => 'Version bumped (patch).',
                    'user_updated'    => 'User saved (role, email, menu access).',
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
                if (!empty($_GET['crm_locked'])): ?>
                <div class="notice notice-warning admin-main-notice"><p style="margin:0;">That lead is already Call done. Only pending leads can change status here.</p></div>
                <?php endif;
                if (!empty($_GET['contact_form_fields_err']) && $mainTab === 'contact_form'): ?>
                <div class="notice notice-error admin-main-notice"><p style="margin:0;">Custom fields JSON is invalid or empty. Fix <strong>Field definitions (JSON)</strong> (or turn off <strong>Use custom fields</strong>). Other contact form and email settings were saved.</p></div>
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
                        <?php if (!$cmsPagesReadOnly): ?>
                        <a href="admin.php" class="button" style="height:26px;font-size:11px;padding:0 10px;">+ New</a>
                        <?php endif; ?>
                    </div>
                    <div class="pages-list">
                        <?php foreach ($allPages as $p): ?>
                        <a href="admin.php?edit=<?php echo htmlspecialchars($p['slug'], ENT_QUOTES, 'UTF-8'); ?>" class="pages-list-item <?php echo (isset($_GET['edit']) && $_GET['edit'] === $p['slug']) ? 'is-active' : ''; ?>">
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
                                <span onclick="event.preventDefault();event.stopPropagation();window.open('<?php echo htmlspecialchars('download_page.php?slug=' . rawurlencode($p['slug']), ENT_QUOTES); ?>','_blank')">Download</span>
                                <?php if (!$cmsPagesReadOnly): ?>
                                <form method="post" action="admin.php" style="display:inline;margin:0;" onclick="event.stopPropagation();" onsubmit="return confirm('Trash this page?');">
                                    <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="hidden" name="post_delete_page" value="1">
                                    <input type="hidden" name="delete_slug" value="<?php echo htmlspecialchars($p['slug']); ?>">
                                    <button type="submit" class="linklike" style="background:none;border:none;padding:0;font:inherit;color:inherit;cursor:pointer;" aria-label="Trash page">Trash</button>
                                </form>
                                <?php endif; ?>
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
                    <p class="description" style="margin:0 0 16px;font-size:13px;color:var(--ink3);line-height:1.5;"><?php echo $cmsPagesReadOnly ? 'Trashed pages are listed here for reference. Your role is view-only — you cannot restore or delete permanently.' : 'Trashed pages are hidden from the public site. <strong>Restore</strong> moves a page back, or <strong>Delete permanently</strong> removes its file (cannot be undone).'; ?></p>
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
                                <?php if (!$cmsPagesReadOnly): ?>
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
                                <?php endif; ?>
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
                                <?php if (!empty($u['email'])): ?>&middot; <?php echo htmlspecialchars($u['email']); ?><?php endif; ?>
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
                    <?php $st = getSiteSettings(); ?>
                    <div class="postbox admin-form-simple" style="margin-bottom:16px;">
                        <div class="postbox-inner">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                            <input type="hidden" name="admin_return_tab" value="settings">
                            <input type="hidden" name="header_logo_url" value="<?php echo htmlspecialchars($st['header_logo_url'] ?? ''); ?>">
                            <div class="form-group">
                                <label for="st-brand">Site name</label>
                                <input type="text" id="st-brand" name="brand" class="wp-input" value="<?php echo htmlspecialchars($st['brand'] ?? ''); ?>" placeholder="Your business name">
                            </div>
                            <div class="form-group">
                                <label for="st-tagline">Tagline <span class="field-hint" style="font-weight:400;">(optional)</span></label>
                                <input type="text" id="st-tagline" name="site_tagline" class="wp-input" value="<?php echo htmlspecialchars($st['site_tagline'] ?? ''); ?>" placeholder="Short line under your site name">
                            </div>
                            <?php $logoResolved = cms_header_logo_url_resolved(); ?>
                            <div class="form-group">
                                <span class="site-logo-uploader-label">Site logo</span>
                                <div class="site-logo-uploader">
                                    <div class="site-logo-uploader__preview" id="site-logo-preview-wrap">
                                        <?php if ($logoResolved !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($logoResolved); ?>" alt="" class="site-logo-uploader__img" id="site-logo-preview-img" width="160" height="160" decoding="async">
                                        <?php else: ?>
                                        <div class="site-logo-uploader__placeholder" id="site-logo-placeholder">No logo selected</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="site-logo-uploader__actions">
                                        <label class="button site-logo-uploader__pick">
                                            <span class="site-logo-uploader__pick-text"><?php echo $logoResolved !== '' ? 'Replace image' : 'Select image'; ?></span>
                                            <input type="file" id="st-header-logo-file" name="header_logo_file" class="site-logo-uploader__input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,.jpg,.jpeg,.png,.gif,.webp,.svg" aria-label="Upload logo image">
                                        </label>
                                        <?php if ($logoResolved !== ''): ?>
                                        <label class="site-logo-uploader__remove-label">
                                            <input type="checkbox" name="header_logo_clear" value="1" id="st-header-logo-clear"> Remove logo
                                        </label>
                                        <?php endif; ?>
                                    </div>
                                    <p class="field-hint site-logo-uploader__hint">PNG, JPG, WebP, GIF, or SVG · max 3&nbsp;MB</p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="st-og">Social share image</label>
                                <input type="url" id="st-og" name="default_og_image" class="wp-input" value="<?php echo htmlspecialchars($st['default_og_image'] ?? ''); ?>" placeholder="https://… (full URL)">
                                <p class="field-hint">Default image when links are shared (Open Graph).</p>
                            </div>
                            <div class="form-group">
                                <label for="st-lang">Language code</label>
                                <input type="text" id="st-lang" name="default_lang" class="wp-input" value="<?php echo htmlspecialchars($st['default_lang'] ?? 'en'); ?>" maxlength="10" placeholder="en" style="max-width:7rem;">
                            </div>
                            <div class="form-group">
                                <label for="st-robots">Extra robots.txt lines</label>
                                <textarea id="st-robots" name="robots_extra" class="wp-input" style="height:72px;min-height:72px;"><?php echo htmlspecialchars($st['robots_extra'] ?? ''); ?></textarea>
                            </div>
                            <input type="hidden" name="maintenance_mode" value="0">
                            <div class="maint-row">
                                <input type="checkbox" name="maintenance_mode" id="st-maint" value="1" <?php echo !empty($st['maintenance_mode']) ? 'checked' : ''; ?>>
                                <label for="st-maint">Maintenance mode — visitors see a short “updating” message instead of your pages.</label>
                            </div>
                            <button type="submit" name="save_site_settings" class="button button-primary">Save</button>
                        </form>
                        </div>
                    </div>
                </div>

                <!-- HTML TAGS: head, body open, footer (single screen) -->
                <div id="html_tags-panel" class="wp-panel wp-panel-wide <?php echo $mainTab === 'html_tags' ? 'active' : ''; ?>">
                    <h1 class="wp-heading-inline">HTML Tags</h1>
                    <div class="postbox admin-form-simple" style="margin-bottom:16px;">
                        <div class="postbox-inner">
                            <form method="post">
                                <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="admin_return_tab" value="html_tags">
                                <div class="form-group">
                                    <label for="hx-head">&lt;head&gt; snippets</label>
                                    <p class="field-hint" style="margin-bottom:8px;">End of <code>&lt;head&gt;</code> — analytics, meta, pixels.</p>
                                    <textarea id="hx-head" name="analytics_head_html" class="wp-input" style="height:120px;min-height:120px;" placeholder="&lt;script&gt;…&lt;/script&gt;"><?php echo htmlspecialchars($st['analytics_head_html'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="hx-body-open">After &lt;body&gt;</label>
                                    <p class="field-hint" style="margin-bottom:8px;">Right after the opening body tag.</p>
                                    <textarea id="hx-body-open" name="inject_body_open_html" class="wp-input" style="height:100px;min-height:100px;"><?php echo htmlspecialchars($st['inject_body_open_html'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="hx-footer">Footer</label>
                                    <p class="field-hint" style="margin-bottom:8px;">Before <code>&lt;/body&gt;</code> — widgets, extra scripts.</p>
                                    <textarea id="hx-footer" name="inject_footer_html" class="wp-input" style="height:120px;min-height:120px;"><?php echo htmlspecialchars($st['inject_footer_html'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" name="save_site_settings" class="button button-primary">Save</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- CALL NOW / CONTACT CTA PANEL -->
                <div id="contact-panel" class="wp-panel wp-panel-wide <?php echo $mainTab === 'contact' ? 'active' : ''; ?>">
                    <h1 class="wp-heading-inline">Call now &amp; WhatsApp</h1>
                    <?php
                    $ct = getSiteSettings();
                    $ctSl = (($ct['sticky_cta_layout'] ?? 'full') === 'split') ? 'split' : 'full';
                    $ctaCallC1 = cms_sanitize_hex_color($ct['cta_call_color'] ?? '', '#1d4ed8');
                    $ctaCallC2 = cms_sanitize_hex_color($ct['cta_call_color2'] ?? '', '#1e40af');
                    $ctaCallLabel = cms_sanitize_cta_label($ct['cta_call_label'] ?? '', 'Call');
                    ?>
                    <div class="postbox admin-form-simple" style="margin-bottom:16px;">
                        <div class="postbox-inner">
                            <form method="post" id="contact-cta-form">
                                <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                <div class="form-group">
                                    <span class="form-section-title">Buttons</span>
                                    <input type="hidden" name="cta_enable_call" value="0">
                                    <input type="hidden" name="cta_enable_whatsapp" value="0">
                                    <input type="hidden" name="cta_sticky_desktop" value="0">
                                    <div class="contact-cta-checks">
                                        <label class="contact-cta-check">
                                            <input type="checkbox" name="cta_enable_call" value="1" <?php echo !empty($ct['cta_enable_call']) ? 'checked' : ''; ?>>
                                            <span>Call</span>
                                        </label>
                                        <label class="contact-cta-check">
                                            <input type="checkbox" name="cta_enable_whatsapp" value="1" <?php echo !empty($ct['cta_enable_whatsapp']) ? 'checked' : ''; ?>>
                                            <span>WhatsApp</span>
                                        </label>
                                        <label class="contact-cta-check">
                                            <input type="checkbox" name="cta_sticky_desktop" value="1" <?php echo !empty($ct['cta_sticky_desktop']) ? 'checked' : ''; ?>>
                                            <span>Bottom bar on desktop</span>
                                        </label>
                                    </div>
                                    <p class="field-hint" style="margin-top:4px;">You can switch off Call, WhatsApp, or both — hidden buttons won’t show on the site.</p>
                                </div>
                                <div class="form-group">
                                    <label for="contact_phone">Phone number</label>
                                    <input type="text" id="contact_phone" name="contact_phone" class="wp-input" value="<?php echo htmlspecialchars($ct['phone'] ?? ''); ?>" placeholder="9987842957" autocomplete="tel">
                                </div>
                                <div class="form-group">
                                    <label for="cta_call_label">Call button label</label>
                                    <input type="text" id="cta_call_label" name="cta_call_label" class="wp-input" maxlength="32" value="<?php echo htmlspecialchars($ctaCallLabel, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Call" autocomplete="off">
                                </div>
                                <div class="form-group">
                                    <label for="contact_whatsapp">WhatsApp number</label>
                                    <input type="text" id="contact_whatsapp" name="contact_whatsapp" class="wp-input" value="<?php echo htmlspecialchars($ct['whatsapp'] ?? ''); ?>" placeholder="+91 9987842957" inputmode="tel" autocomplete="tel">
                                </div>
                                <div class="form-group">
                                    <span class="form-section-title">Call button gradient</span>
                                    <div class="cta-color-row contact-cta-colors">
                                        <label class="contact-cta-color" for="cta_call_color">
                                            <span>Top</span>
                                            <input type="color" id="cta_call_color" name="cta_call_color" value="<?php echo htmlspecialchars($ctaCallC1); ?>" class="cta-color-input" title="Top color">
                                        </label>
                                        <label class="contact-cta-color" for="cta_call_color2">
                                            <span>Bottom</span>
                                            <input type="color" id="cta_call_color2" name="cta_call_color2" value="<?php echo htmlspecialchars($ctaCallC2); ?>" class="cta-color-input" title="Bottom color">
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <span class="form-section-title">Bottom bar layout</span>
                                    <div class="cta-layout-radios contact-cta-layout-radios">
                                        <label class="contact-cta-radio">
                                            <input type="radio" name="sticky_cta_layout" value="full" <?php echo $ctSl === 'full' ? 'checked' : ''; ?>>
                                            <span>Full width</span>
                                        </label>
                                        <label class="contact-cta-radio">
                                            <input type="radio" name="sticky_cta_layout" value="split" <?php echo $ctSl === 'split' ? 'checked' : ''; ?>>
                                            <span>Side by side</span>
                                        </label>
                                    </div>
                                    <p class="field-hint" style="margin-bottom:8px;">Preview</p>
                                    <div id="cta-layout-preview-phone" class="cta-layout-preview-phone" aria-hidden="true" style="--cta-preview-call-a: <?php echo htmlspecialchars($ctaCallC1, ENT_QUOTES, 'UTF-8'); ?>; --cta-preview-call-b: <?php echo htmlspecialchars($ctaCallC2, ENT_QUOTES, 'UTF-8'); ?>;">
                                        <div class="cta-layout-preview-notch"></div>
                                        <div id="cta-layout-preview-inner" class="cta-layout-preview-inner cta-layout-preview-inner--<?php echo htmlspecialchars($ctSl); ?>">
                                            <span class="cta-pfake cta-pfake--call"><span id="cta-preview-call-label"><?php echo htmlspecialchars($ctaCallLabel, ENT_QUOTES, 'UTF-8'); ?></span></span>
                                            <span class="cta-pfake cta-pfake--wa">WhatsApp</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="field-hint" style="margin:0 0 16px;">Clear a number to use the default for that field in <code>config.php</code>.</p>
                                <button type="submit" name="save_contact_cta" class="button button-primary">Save</button>
                            </form>
                        </div>
                    </div>
                    <p class="field-hint" style="margin:0;">Configure the page contact form (shortcode, email, SMTP) under <a href="admin.php?tab=contact_form">Contact form</a> in the menu.</p>
                </div>

                <!-- CONTACT FORM PANEL -->
                <div id="contact_form-panel" class="wp-panel wp-panel-wide <?php echo $mainTab === 'contact_form' ? 'active' : ''; ?>">
                    <h1 class="wp-heading-inline">Contact form</h1>
                    <?php $cf = getSiteSettings(); ?>
                    <div class="postbox admin-form-simple admin-form-simple--contact-form" style="margin-bottom:16px;">
                        <div class="postbox-inner">
                            <p class="field-hint" style="margin:0 0 16px;">In any page HTML, paste <code>[cms_contact_form]</code> or <code>[cms_contact_form title="Get in touch"]</code> to show the form. Submissions go to the address in the right column. By default the form uses: Full Name, Mobile, Email, and Product. Enable <strong>Use custom fields</strong> to replace them with your own list (JSON).</p>
                            <form method="post">
                                <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                <div class="contact-form-admin-cols">
                                    <div class="contact-form-admin-col contact-form-admin-col--fields">
                                        <span class="form-section-title" style="margin-top:0;">Form fields</span>
                                        <div class="form-group">
                                            <input type="hidden" name="contact_form_use_custom" value="0">
                                            <label class="contact-cta-check" style="display:inline-flex;align-items:flex-start;gap:8px;margin-bottom:12px;">
                                                <input type="checkbox" name="contact_form_use_custom" value="1" <?php echo !empty($cf['contact_form_use_custom']) ? 'checked' : ''; ?> style="margin-top:2px;">
                                                <span><strong>Use custom fields</strong> — when checked, the <strong>Field definitions</strong> JSON replaces the default fields (Full Name, Mobile, Email, Product) on the site and in CRM.</span>
                                            </label>
                                            <label for="contact_form_fields_json" style="display:block;font-size:13px;font-weight:600;color:var(--ink);margin:0 0 6px;">Field definitions (JSON)</label>
                                            <textarea id="contact_form_fields_json" name="contact_form_fields_json" class="wp-input contact-form-admin-fields-json" rows="14" spellcheck="false"><?php echo htmlspecialchars(json_encode(cms_contact_form_fields_json_template(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <div class="contact-form-field-samples" aria-label="Field type samples for JSON">
                                                <div class="contact-form-field-samples__head">Field type samples <span class="field-hint" style="display:inline;font-weight:400;">— copy pieces into your array; <code>name</code> must start with a letter; 1–20 fields; reserved: <code>cms_cf_token</code>, <code>return_url</code>, <code>cms_hp_notes</code></span></div>
                                                <p class="field-hint contact-form-field-samples__rules" style="margin:0 0 10px;">Every object needs <code>name</code>, <code>label</code>, <code>type</code>, <code>required</code>. Use <code>dropdown</code> (or <code>select</code>) with an <code>options</code> array. Use <code>number</code> with optional <code>min</code>, <code>max</code>, <code>step</code>. Add an <code>email</code> field for Reply-To on outgoing mail.</p>
                                                <div class="contact-form-field-samples__grid">
                                                    <div class="contact-form-field-samples__item">
                                                        <span class="contact-form-field-samples__label">Full JSON array</span>
                                                        <pre class="contact-form-field-samples__pre"><code><?php echo htmlspecialchars('[
  {"name":"full_name","label":"Name","type":"text","required":true},
  {"name":"qty","label":"Quantity","type":"number","required":false,"min":1,"max":99},
  {"name":"email","label":"Email","type":"email","required":true},
  {"name":"phone","label":"Phone","type":"tel","required":false},
  {"name":"topic","label":"Topic","type":"dropdown","required":true,"options":["Sales","Support","Other"]},
  {"name":"message","label":"Message","type":"textarea","required":true}
]', ENT_QUOTES, 'UTF-8'); ?></code></pre>
                                                    </div>
                                                    <div class="contact-form-field-samples__item">
                                                        <span class="contact-form-field-samples__label">text</span>
                                                        <pre class="contact-form-field-samples__pre"><code><?php echo htmlspecialchars('{"name":"company","label":"Company","type":"text","required":true}', ENT_QUOTES, 'UTF-8'); ?></code></pre>
                                                    </div>
                                                    <div class="contact-form-field-samples__item">
                                                        <span class="contact-form-field-samples__label">number</span>
                                                        <pre class="contact-form-field-samples__pre"><code><?php echo htmlspecialchars('{"name":"guests","label":"Guests","type":"number","required":false,"min":1,"max":10,"step":1}', ENT_QUOTES, 'UTF-8'); ?></code></pre>
                                                    </div>
                                                    <div class="contact-form-field-samples__item">
                                                        <span class="contact-form-field-samples__label">email</span>
                                                        <pre class="contact-form-field-samples__pre"><code><?php echo htmlspecialchars('{"name":"email","label":"Email","type":"email","required":true}', ENT_QUOTES, 'UTF-8'); ?></code></pre>
                                                    </div>
                                                    <div class="contact-form-field-samples__item">
                                                        <span class="contact-form-field-samples__label">tel</span>
                                                        <pre class="contact-form-field-samples__pre"><code><?php echo htmlspecialchars('{"name":"mobile","label":"Mobile","type":"tel","required":true}', ENT_QUOTES, 'UTF-8'); ?></code></pre>
                                                    </div>
                                                    <div class="contact-form-field-samples__item">
                                                        <span class="contact-form-field-samples__label">textarea</span>
                                                        <pre class="contact-form-field-samples__pre"><code><?php echo htmlspecialchars('{"name":"message","label":"Message","type":"textarea","required":true}', ENT_QUOTES, 'UTF-8'); ?></code></pre>
                                                    </div>
                                                    <div class="contact-form-field-samples__item">
                                                        <span class="contact-form-field-samples__label">dropdown</span>
                                                        <pre class="contact-form-field-samples__pre"><code><?php echo htmlspecialchars('{"name":"service","label":"Service","type":"dropdown","required":true,"options":["Design","SEO","Hosting"]}', ENT_QUOTES, 'UTF-8'); ?></code></pre>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="contact-form-admin-col contact-form-admin-col--email">
                                        <span class="form-section-title" style="margin-top:0;">Email setup</span>
                                        <p class="field-hint" style="margin:0 0 14px;">Where messages go and how they are sent.</p>
                                        <div class="form-group">
                                            <label for="contact_form_to_email">Send submissions to</label>
                                            <input type="email" id="contact_form_to_email" name="contact_form_to_email" class="wp-input" value="<?php echo htmlspecialchars($cf['contact_form_to_email'] ?? ''); ?>" placeholder="you@example.com" autocomplete="email">
                                        </div>
                                        <div class="form-group">
                                            <label for="contact_form_subject">Email subject</label>
                                            <input type="text" id="contact_form_subject" name="contact_form_subject" class="wp-input" value="<?php echo htmlspecialchars($cf['contact_form_subject'] ?? 'New contact from {site}'); ?>" placeholder="New contact from {site}">
                                            <p class="field-hint"><code>{site}</code> is replaced with your site name.</p>
                                        </div>
                                        <div class="form-group">
                                            <span class="form-section-title">Sender (PHP <code>mail()</code> and SMTP)</span>
                                            <p class="field-hint" style="margin-bottom:8px;"><strong>Simple setup (no SMTP):</strong> leave “Use SMTP” off. The server sends mail with PHP <code>mail()</code> — free and fine on hosts like Hostinger. Set <strong>From email</strong> to an address on <strong>your domain</strong> (e.g. <code>info@yourdomain.com</code>). Do not use Gmail or another external address as From — deliverability will fail. The visitor’s email is added as <strong>Reply-To</strong> automatically when they enter an email field.</p>
                                        </div>
                                        <div class="form-group">
                                            <label for="smtp_from_email">From email</label>
                                            <input type="email" id="smtp_from_email" name="smtp_from_email" class="wp-input" value="<?php echo htmlspecialchars($cf['smtp_from_email'] ?? ''); ?>" placeholder="info@yourdomain.com" autocomplete="off">
                                        </div>
                                        <div class="form-group">
                                            <label for="smtp_from_name">From name <span class="field-hint" style="font-weight:400;">(optional)</span></label>
                                            <input type="text" id="smtp_from_name" name="smtp_from_name" class="wp-input" value="<?php echo htmlspecialchars($cf['smtp_from_name'] ?? ''); ?>" placeholder="<?php echo htmlspecialchars($cf['brand'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <span class="form-section-title">SMTP (optional)</span>
                                            <p class="field-hint" style="margin-bottom:8px;">Turn on only if your host requires SMTP, or you use Gmail / SendGrid / transactional mail. If off, <code>mail()</code> uses the sender fields above (or <code>noreply@your-site-domain</code> if From is empty).</p>
                                            <input type="hidden" name="smtp_enabled" value="0">
                                            <label class="contact-cta-check" style="display:inline-flex;align-items:center;gap:8px;margin-bottom:12px;">
                                                <input type="checkbox" name="smtp_enabled" value="1" <?php echo !empty($cf['smtp_enabled']) ? 'checked' : ''; ?>>
                                                <span>Use SMTP</span>
                                            </label>
                                        </div>
                                        <div class="form-group">
                                            <label for="smtp_host">SMTP host</label>
                                            <input type="text" id="smtp_host" name="smtp_host" class="wp-input" value="<?php echo htmlspecialchars($cf['smtp_host'] ?? ''); ?>" placeholder="smtp.example.com" autocomplete="off">
                                        </div>
                                        <div class="form-group" style="display:flex;gap:16px;flex-wrap:wrap;">
                                            <div style="flex:1;min-width:120px;">
                                                <label for="smtp_port">Port</label>
                                                <input type="number" id="smtp_port" name="smtp_port" class="wp-input" min="1" max="65535" value="<?php echo (int) ($cf['smtp_port'] ?? 587); ?>">
                                            </div>
                                            <div style="flex:1;min-width:160px;">
                                                <label for="smtp_encryption">Encryption</label>
                                                <select id="smtp_encryption" name="smtp_encryption" class="wp-input">
                                                    <?php $encCf = $cf['smtp_encryption'] ?? 'tls'; ?>
                                                    <option value="none" <?php echo $encCf === 'none' ? 'selected' : ''; ?>>None</option>
                                                    <option value="tls" <?php echo $encCf === 'tls' ? 'selected' : ''; ?>>TLS (STARTTLS, often 587)</option>
                                                    <option value="ssl" <?php echo $encCf === 'ssl' ? 'selected' : ''; ?>>SSL (often 465)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="smtp_user">SMTP username</label>
                                            <input type="text" id="smtp_user" name="smtp_user" class="wp-input" value="<?php echo htmlspecialchars($cf['smtp_user'] ?? ''); ?>" autocomplete="username">
                                        </div>
                                        <div class="form-group">
                                            <label for="smtp_pass">SMTP password</label>
                                            <input type="password" id="smtp_pass" name="smtp_pass" class="wp-input" value="" placeholder="Leave blank to keep current" autocomplete="new-password">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="save_contact_form" class="button button-primary">Save contact form</button>
                            </form>
                        </div>
                    </div>
                    <p class="field-hint" style="margin:0;">Submitted entries from the site form are listed under <a href="admin.php?tab=crm">CRM</a> in the menu.</p>
                </div>

                <!-- CRM: contact form leads -->
                <div id="crm-panel" class="wp-panel wp-panel-wide <?php echo $mainTab === 'crm' ? 'active' : ''; ?>">
                    <h1 class="wp-heading-inline">CRM</h1>
                    <p class="field-hint crm-panel-intro" style="margin:-8px 0 16px;">Leads from <code>[cms_contact_form]</code>. <strong>Call</strong> marks <strong>Call done</strong> when you dial (pending only). Use <strong>Set status</strong> while the lead is <strong>Pending</strong>.</p>
                    <hr class="wp-header-end">
                    <?php
                    $crmFilter = isset($_GET['crm_filter']) ? strtolower(trim((string) $_GET['crm_filter'])) : 'all';
                    if (in_array($crmFilter, ['new', 'followup'], true)) {
                        $crmFilter = 'pending';
                    }
                    if (!in_array($crmFilter, array_merge(['all'], cms_crm_status_values()), true)) {
                        $crmFilter = 'all';
                    }
                    $crmQ = isset($_GET['crm_q']) ? trim((string) $_GET['crm_q']) : '';
                    $crmDatePreset = isset($_GET['crm_date']) ? strtolower(trim((string) $_GET['crm_date'])) : 'all';
                    if (!in_array($crmDatePreset, ['all', 'today', 'custom'], true)) {
                        $crmDatePreset = 'all';
                    }
                    $crmDateFrom = cms_crm_sanitize_date_ymd((string) ($_GET['crm_from'] ?? ''));
                    $crmDateTo = cms_crm_sanitize_date_ymd((string) ($_GET['crm_to'] ?? ''));
                    $crmAll = cms_contact_get_submissions();
                    $crmSubs = cms_crm_filter_submissions($crmAll, $crmFilter, $crmQ, $crmDatePreset, $crmDateFrom, $crmDateTo);
                    $crmCols = cms_contact_form_fields();
                    $crmStatusLabels = ['pending' => 'Pending', 'done' => 'Call done'];
                    ?>
                    <script>
                    window.CMS_CRM_MARK_URL = <?php echo json_encode(cms_url('crm_mark_call.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                    window.CMS_ADMIN_CSRF = <?php echo json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                    </script>
                    <div class="postbox" style="margin-bottom:16px;">
                        <h2 class="postbox-header">Leads</h2>
                        <div class="postbox-inner crm-postbox-inner">
                            <form method="get" action="admin.php" class="crm-filters">
                                <input type="hidden" name="tab" value="crm">
                                <div class="crm-filters__group">
                                    <label for="crm-filter-select" class="crm-filters__label">Status</label>
                                    <select id="crm-filter-select" name="crm_filter" class="wp-input crm-filters__control">
                                        <option value="all" <?php echo $crmFilter === 'all' ? 'selected' : ''; ?>>All</option>
                                        <option value="pending" <?php echo $crmFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="done" <?php echo $crmFilter === 'done' ? 'selected' : ''; ?>>Call done</option>
                                    </select>
                                </div>
                                <div class="crm-filters__group">
                                    <label for="crm-date-preset" class="crm-filters__label">Date</label>
                                    <select id="crm-date-preset" name="crm_date" class="wp-input crm-filters__control">
                                        <option value="all" <?php echo $crmDatePreset === 'all' ? 'selected' : ''; ?>>All dates</option>
                                        <option value="today" <?php echo $crmDatePreset === 'today' ? 'selected' : ''; ?>>Today</option>
                                        <option value="custom" <?php echo $crmDatePreset === 'custom' ? 'selected' : ''; ?>>Custom</option>
                                    </select>
                                </div>
                                <div id="crm-custom-dates" class="crm-filters__group crm-filters__custom-range" <?php echo $crmDatePreset !== 'custom' ? 'hidden' : ''; ?> aria-hidden="<?php echo $crmDatePreset === 'custom' ? 'false' : 'true'; ?>">
                                    <span class="crm-filters__label">Custom range</span>
                                    <div class="crm-filters__date-inputs">
                                        <label class="crm-filters__date-label"><span class="screen-reader-text">From</span>
                                            <input type="date" id="crm-from" name="crm_from" class="wp-input" value="<?php echo htmlspecialchars($crmDateFrom); ?>" title="From date" aria-label="From date">
                                        </label>
                                        <span class="crm-filters__date-sep" aria-hidden="true">–</span>
                                        <label class="crm-filters__date-label"><span class="screen-reader-text">To</span>
                                            <input type="date" id="crm-to" name="crm_to" class="wp-input" value="<?php echo htmlspecialchars($crmDateTo); ?>" title="To date" aria-label="To date">
                                        </label>
                                    </div>
                                </div>
                                <div class="crm-filters__group crm-filters__group--grow">
                                    <label for="crm-q" class="crm-filters__label">Search</label>
                                    <input type="search" id="crm-q" name="crm_q" class="wp-input" placeholder="Name, email, mobile, product…" value="<?php echo htmlspecialchars($crmQ); ?>" autocomplete="off">
                                </div>
                                <div class="crm-filters__group crm-filters__group--actions">
                                    <label class="crm-filters__label" for="crm-apply-filter-btn">Actions</label>
                                    <div class="crm-filters__btn-row">
                                        <button type="submit" id="crm-apply-filter-btn" class="button button-primary crm-filters__submit">Apply filter</button>
                                        <a href="admin.php?tab=crm" class="button crm-filters__clear">Clear filters</a>
                                    </div>
                                </div>
                            </form>
                            <script>
                            (function () {
                                var sel = document.getElementById('crm-date-preset');
                                var wrap = document.getElementById('crm-custom-dates');
                                var from = document.getElementById('crm-from');
                                var to = document.getElementById('crm-to');
                                if (!sel || !wrap) return;
                                function sync() {
                                    var on = sel.value === 'custom';
                                    wrap.hidden = !on;
                                    wrap.setAttribute('aria-hidden', on ? 'false' : 'true');
                                    if (from) from.disabled = !on;
                                    if (to) to.disabled = !on;
                                }
                                sel.addEventListener('change', sync);
                                sync();
                            })();
                            </script>
                            <p class="field-hint" style="margin:0 0 12px;">Showing <strong><?php echo (int) count($crmSubs); ?></strong> of <strong><?php echo (int) count($crmAll); ?></strong> · up to 500 stored · dates use the server timezone.</p>
                            <?php if ($crmAll === []): ?>
                            <p style="margin:0;color:var(--mid);font-size:13px;">No submissions yet.</p>
                            <?php elseif ($crmSubs === []): ?>
                            <p style="margin:0;color:var(--mid);font-size:13px;">No leads match this filter. Try <a href="admin.php?tab=crm">clearing filters</a>.</p>
                            <?php else: ?>
                            <div class="crm-leads-table-wrap" role="region" aria-label="Leads list">
                            <table class="crm-leads-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Date</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Set status</th>
                                        <th scope="col">Call</th>
                                        <?php foreach ($crmCols as $fc): ?>
                                        <th scope="col"><?php echo htmlspecialchars($fc['label']); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($crmSubs as $sr):
                                        $st = $sr['crm_status'] ?? 'pending';
                                        $mob = (string) ($sr['fields']['mobile'] ?? '');
                                        $tel = cms_crm_tel_href($mob);
                                        $isDone = ($st === 'done');
                                        ?>
                                    <tr class="crm-lead-row">
                                        <td class="crm-leads-table__date" data-label="Date"><?php echo htmlspecialchars(cms_crm_format_lead_date($sr['at'])); ?></td>
                                        <td class="crm-leads-table__status" data-label="Status"><span class="crm-badge crm-badge--<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($crmStatusLabels[$st] ?? $st); ?></span></td>
                                        <td class="crm-leads-table__setstatus" data-label="Set status">
                                            <?php if ($st === 'pending'): ?>
                                            <form method="post" class="crm-status-form">
                                                <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                                <input type="hidden" name="crm_submission_id" value="<?php echo htmlspecialchars($sr['id']); ?>">
                                                <input type="hidden" name="crm_return_filter" value="<?php echo htmlspecialchars($crmFilter); ?>">
                                                <input type="hidden" name="crm_return_q" value="<?php echo htmlspecialchars($crmQ); ?>">
                                                <input type="hidden" name="crm_return_date" value="<?php echo htmlspecialchars($crmDatePreset); ?>">
                                                <input type="hidden" name="crm_return_date_from" value="<?php echo htmlspecialchars($crmDateFrom); ?>">
                                                <input type="hidden" name="crm_return_date_to" value="<?php echo htmlspecialchars($crmDateTo); ?>">
                                                <select name="crm_status" class="wp-input crm-status-form__select" aria-label="Set lead status">
                                                    <option value="pending" selected>Pending</option>
                                                    <option value="done">Call done</option>
                                                </select>
                                                <button type="submit" name="crm_manual_status" value="1" class="button crm-status-form__btn">Save</button>
                                            </form>
                                            <?php else: ?>
                                            <span class="crm-status-locked" role="status" title="Status is set to Call done; change is disabled here">
                                                <span class="crm-status-locked__icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
                                                <span class="crm-status-locked__text">Locked</span>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="crm-leads-table__call" data-label="Call">
                                            <?php if ($tel !== ''): ?>
                                            <a href="<?php echo htmlspecialchars($tel); ?>" class="button crm-call-btn" data-crm-id="<?php echo htmlspecialchars($sr['id']); ?>" data-crm-done="<?php echo $isDone ? '1' : '0'; ?>">Call</a>
                                            <?php else: ?>
                                            <span class="button crm-call-btn crm-call-btn--disabled" title="No dialable digits in mobile field">Call</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach ($crmCols as $fc):
                                            $fk = $fc['name'];
                                            $cell = (string) ($sr['fields'][$fk] ?? '');
                                            $cellTrim = trim($cell);
                                            ?>
                                        <td class="crm-leads-table__cell" data-label="<?php echo htmlspecialchars($fc['label'], ENT_QUOTES, 'UTF-8'); ?>"><?php if ($cellTrim === ''): ?><span class="crm-field-empty" aria-label="No data">---</span><?php else: ?><?php echo htmlspecialchars($cell); ?><?php endif; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                            <?php endif; ?>
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
                    <?php if ($pageEditorEmptyReadonly): ?>
                    <div class="edit-header">
                        <h3 style="margin:0; font-size:14px;">Pages (view only)</h3>
                    </div>
                    <div class="edit-body">
                        <p class="field-hint" style="max-width:480px;line-height:1.55;font-size:13px;">Your role can open pages to review content but cannot create or edit them. Select a page on the left to view its fields.</p>
                    </div>
                    <?php else: ?>
                    <form id="page-editor-form" action="admin.php" method="POST" class="<?php echo $pageEditorReadonly ? 'page-editor--readonly' : ''; ?>" style="display:contents;" data-is-new="<?php echo $editData ? '0' : '1'; ?>" data-read-only="<?php echo $pageEditorReadonly ? '1' : '0'; ?>"<?php echo $pageEditorReadonly ? ' onsubmit="return false;"' : ''; ?>>
                        <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="current_slug" value="<?php echo $editData ? htmlspecialchars($editData['slug']) : ''; ?>">
                        <div class="edit-header">
                            <h3 style="margin:0; font-size:14px;"><?php
                                if ($pageEditorReadonly) {
                                    echo 'View page';
                                } elseif ($editData) {
                                    echo 'Editing Design';
                                } else {
                                    echo 'Compose New';
                                }
                            ?></h3>
                            <div class="edit-header-actions" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                                <?php if ($editData): ?>
                                <a href="download_page.php?slug=<?php echo urlencode($editData['slug']); ?>" class="button edit-header-btn-secondary" target="_blank" rel="noopener" title="One .html file — CSS in &lt;style&gt;, page HTML in &lt;body&gt;">Download HTML</a>
                                <?php endif; ?>
                                <?php if (!$pageEditorReadonly): ?>
                                <button type="submit" name="create_page" class="button button-primary">Save</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="edit-body">
                            <?php if ($pageEditorReadonly): ?>
                            <p class="field-hint" style="margin:0 0 14px;font-size:12px;">Read-only — ask an administrator to change this page.</p>
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="page_title">TITLE</label>
                                <input type="text" name="page_title" id="page_title" class="wp-input" placeholder="Page title" value="<?php echo $editData ? htmlspecialchars($editData['title'] ?? ucwords(str_replace('-', ' ', $editData['slug']))) : ''; ?>"<?php echo $pageEditorReadonly ? ' readonly' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="page_slug">SLUG <span class="description" style="display:inline;font-weight:400;margin-left:4px;">(URL — edit anytime; Save applies)</span></label>
                                <input type="text" name="slug" id="page_slug" class="wp-input" autocomplete="off" value="<?php echo $editData ? htmlspecialchars($slugForForm) : ''; ?>" placeholder="e.g. my-page" pattern="[a-z0-9\-]*" title="Lowercase letters, numbers, and hyphens only"<?php echo $pageEditorReadonly ? ' readonly' : ''; ?>>
                                <p class="description" style="margin-top:8px;margin-bottom:0;display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                                    <span>Permalink: <code id="permalink_preview" style="font-size:12px;word-break:break-all;"><?php echo htmlspecialchars($editData ? cms_page_url($permalinkPreview) : cms_page_url('your-slug')); ?></code></span>
                                    <?php if (!$pageEditorReadonly): ?>
                                    <button type="button" class="button" id="slug_from_title" style="height:26px;font-size:12px;padding:0 10px;">Regenerate from title</button>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="form-group">
                                <label for="meta_description">Meta description (SEO)</label>
                                <textarea id="meta_description" name="meta_description" class="wp-input" style="height:72px;" maxlength="320" placeholder="Short summary for search results"<?php echo $pageEditorReadonly ? ' readonly' : ''; ?>><?php echo $editData ? htmlspecialchars($editData['meta_description'] ?? '') : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="og_image">Open Graph image URL (optional)</label>
                                <input type="url" id="og_image" name="og_image" class="wp-input" value="<?php echo $editData ? htmlspecialchars($editData['og_image'] ?? '') : ''; ?>" placeholder="https://..."<?php echo $pageEditorReadonly ? ' readonly' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label>HTML MODULES</label>
                                <textarea name="html_content" class="wp-code wp-input"<?php echo $pageEditorReadonly ? ' readonly' : ''; ?>><?php echo $editData ? htmlspecialchars($editData['html']) : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>STYLING (CSS)</label>
                                <textarea name="css_content" class="wp-code wp-input" style="height:150px;"<?php echo $pageEditorReadonly ? ' readonly' : ''; ?>><?php echo $editData ? htmlspecialchars($editData['css']) : ''; ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>STATUS</label>
                                <select name="page_status" class="wp-input"<?php echo $pageEditorReadonly ? ' disabled' : ''; ?>>
                                    <option value="draft" <?php echo ($editData && ($editData['status'] ?? 'draft') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo ($editData && ($editData['status'] ?? 'draft') === 'published') ? 'selected' : ''; ?>>Published</option>
                                </select>
                            </div>
                            <div style="display:flex; align-items:center; gap:10px; background:var(--wh); padding:10px 12px; border:1px solid var(--rule);">
                                <input type="checkbox" name="is_home" id="is_home" <?php echo ($editData && ($editData['is_home'] ?? false)) ? 'checked' : ''; ?> style="width:18px; height:18px;"<?php echo $pageEditorReadonly ? ' disabled' : ''; ?>>
                                <label for="is_home" style="margin:0; font-weight:600; font-size:13px;">Set as front page</label>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
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
                                <label for="new-user-email">Email <span class="field-hint" style="font-weight:400;">(optional — used to sign in)</span></label>
                                <input type="email" id="new-user-email" name="user_email" class="wp-input" autocomplete="email" placeholder="name@example.com">
                            </div>
                            <div class="form-group">
                                <label for="new-role">Role</label>
                                <select id="new-role" name="role" class="wp-input" required>
                                    <option value="Administrator">Administrator</option>
                                    <option value="Normal User" selected>Normal User</option>
                                </select>
                            </div>
                            <?php $newUserMenuChecked = cms_default_menu_allow_normal(); ?>
                            <fieldset id="user-menu-allow-fieldset-new" style="border:1px solid var(--rule-l);padding:12px 14px;margin-top:14px;border-radius:4px;">
                                <legend style="font-size:12px;padding:0 6px;color:var(--ink2);">Menu access</legend>
                                <p class="field-hint" style="margin:0 0 10px;font-size:12px;line-height:1.4;">Sidebar items this user can open. Administrators always have every item (checkboxes ignored). For <strong>Normal User</strong>, Pages and Trash are view-only (no edits or trash actions).</p>
                                <div class="user-menu-allow-grid" style="display:grid;gap:6px;">
                                    <?php foreach ($userNavItemsMenu as $m): ?>
                                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                                        <input type="checkbox" name="menu_allow[]" value="<?php echo htmlspecialchars($m['key']); ?>" class="user-menu-allow-cb" <?php echo in_array($m['key'], $newUserMenuChecked, true) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($m['label']); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
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
                    <?php else:
                    $editMenuChecked = $enorm === 'Administrator'
                        ? cms_admin_menu_keys()
                        : cms_sanitize_menu_allow($editUserData['menu_allow'] ?? []);
                    if ($enorm !== 'Administrator' && $editMenuChecked === []) {
                        $editMenuChecked = cms_default_menu_allow_normal();
                    }
                    ?>
                    <div class="edit-header">
                        <h3 style="margin:0;font-size:14px;"><?php echo htmlspecialchars($euname); ?></h3>
                        <button type="submit" form="user-role-form" name="update_user_role" value="1" class="button button-primary">Save user</button>
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
                            <div class="form-group">
                                <label for="edit-user-email">Email <span class="field-hint" style="font-weight:400;">(optional — used to sign in)</span></label>
                                <input type="email" id="edit-user-email" name="user_email" class="wp-input" autocomplete="email" placeholder="name@example.com" value="<?php echo htmlspecialchars((string) ($editUserData['email'] ?? '')); ?>">
                            </div>
                            <fieldset id="user-menu-allow-fieldset-edit" style="border:1px solid var(--rule-l);padding:12px 14px;margin-top:14px;border-radius:4px;">
                                <legend style="font-size:12px;padding:0 6px;color:var(--ink2);">Menu access</legend>
                                <p class="field-hint" style="margin:0 0 10px;font-size:12px;line-height:1.4;">Sidebar visibility for this user. Administrators always have full access. <strong>Normal User</strong> can view pages and trash but cannot create, edit, trash, restore, or permanently delete pages.</p>
                                <div class="user-menu-allow-grid" style="display:grid;gap:6px;">
                                    <?php foreach ($userNavItemsMenu as $m): ?>
                                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                                        <input type="checkbox" name="menu_allow[]" value="<?php echo htmlspecialchars($m['key']); ?>" class="user-menu-allow-cb" <?php echo in_array($m['key'], $editMenuChecked, true) ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($m['label']); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
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
        (function () {
            try {
                var u = new URL(window.location.href);
                if (!u.searchParams.has('crm_locked')) return;
                u.searchParams.delete('crm_locked');
                var qs = u.searchParams.toString();
                window.history.replaceState({}, '', u.pathname + (qs ? '?' + qs : '') + u.hash);
            } catch (e3) {}
        })();
        (function () {
            function bindMenuAllow(roleSel, fieldsetSel) {
                var role = document.querySelector(roleSel);
                var fs = document.querySelector(fieldsetSel);
                if (!role || !fs) return;
                var grid = fs.querySelector('.user-menu-allow-grid');
                if (!grid) return;
                function sync() {
                    var adm = role.value === 'Administrator';
                    fs.style.opacity = adm ? '0.55' : '1';
                    grid.querySelectorAll('input[type=checkbox]').forEach(function (cb) {
                        cb.disabled = adm;
                        if (adm) {
                            cb.checked = true;
                        }
                    });
                }
                role.addEventListener('change', sync);
                sync();
            }
            bindMenuAllow('#new-role', '#user-menu-allow-fieldset-new');
            bindMenuAllow('#edit-user-role', '#user-menu-allow-fieldset-edit');
        })();
        (function () {
            var input = document.getElementById('st-header-logo-file');
            if (!input) return;
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                if (!f) return;
                try {
                    var url = URL.createObjectURL(f);
                    var wrap = document.getElementById('site-logo-preview-wrap');
                    if (wrap) {
                        wrap.innerHTML = '<img src="' + url + '" alt="" class="site-logo-uploader__img" id="site-logo-preview-img" width="160" height="160" decoding="async">';
                    }
                    var t = document.querySelector('.site-logo-uploader__pick-text');
                    if (t) t.textContent = 'Replace image';
                    var cb = document.getElementById('st-header-logo-clear');
                    if (cb) cb.checked = false;
                } catch (e1) {}
            });
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
                toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
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
            document.addEventListener('click', function (e) {
                var a = e.target.closest && e.target.closest('a.crm-call-btn[data-crm-id]');
                if (!a) return;
                if (a.getAttribute('data-crm-done') === '1') return;
                var href = a.getAttribute('href') || '';
                if (href.indexOf('tel:') !== 0) return;
                e.preventDefault();
                var id = a.getAttribute('data-crm-id');
                var url = window.CMS_CRM_MARK_URL;
                var tok = window.CMS_ADMIN_CSRF;
                if (!url || !tok || !id) {
                    window.location.href = href;
                    return;
                }
                var fd = new FormData();
                fd.append('cms_csrf', tok);
                fd.append('crm_submission_id', id);
                fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
                    .catch(function () {})
                    .then(function () {
                        window.location.href = href;
                    });
            });
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
                var htags = document.getElementById('html_tags-panel');
                if (htags && htags.classList.contains('active')) {
                    var htb = htags.querySelector('button[name="save_site_settings"]');
                    if (htb) { htb.click(); return; }
                }
                var ct = document.getElementById('contact-panel');
                if (ct && ct.classList.contains('active')) {
                    var cb = ct.querySelector('button[name="save_contact_cta"]');
                    if (cb) { cb.click(); return; }
                }
                var cff = document.getElementById('contact_form-panel');
                if (cff && cff.classList.contains('active')) {
                    var cfb = cff.querySelector('button[name="save_contact_form"]');
                    if (cfb) { cfb.click(); return; }
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
                    var pe = document.getElementById('page-editor-form');
                    if (pe && pe.getAttribute('data-read-only') === '1') return;
                    var pb = eb.querySelector('button[name="create_page"]');
                    if (pb) pb.click();
                }
            });
        })();
        (function () {
            var form = document.getElementById('page-editor-form');
            if (!form) return;
            if (form.getAttribute('data-read-only') === '1') return;

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
            if (tab === 'users' || tab === 'config' || tab === 'settings' || tab === 'html_tags' || tab === 'contact' || tab === 'contact_form' || tab === 'crm' || tab === 'trash') {
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
