<?php
include 'config.php';
include 'cms_core.php';
require_once __DIR__ . '/admin_menu.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin.php");
    exit;
}

$sysVer   = getSystemVersion();
$cmsRoot  = realpath(__DIR__);
$msg      = '';
$msgType  = '';
$csrf     = cms_csrf_token();

/** Only these .zip names appear under "Backup Files" (not random zips extracted from imports). */
function backup_is_managed_backup_zip(string $name): bool {
    if (!preg_match('/\.zip$/i', $name)) {
        return false;
    }
    if (preg_match('/^import_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/i', $name)) {
        return true;
    }
    if (preg_match('/^full_system_backup_.+\.zip$/i', $name)) {
        return true;
    }
    if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/i', $name)) {
        return true;
    }
    if (preg_match('/^hard_restore_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/i', $name)) {
        return true;
    }
    return false;
}

function addFolderToZip(ZipArchive $zip, string $folder, string $base): void {
    $handle = opendir($folder);
    if (!$handle) return;
    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') continue;
        $fullPath = $folder . '/' . $entry;
        $relPath  = substr($fullPath, strlen($base) + 1);
        if (is_dir($fullPath)) {
            $zip->addEmptyDir($relPath);
            addFolderToZip($zip, $fullPath, $base);
        } elseif (is_file($fullPath)) {
            $zip->addFile($fullPath, $relPath);
        }
    }
    closedir($handle);
}

// ── Download a backup file ──────────────────────────────
if (isset($_GET['dl_backup'])) {
    $file = basename($_GET['dl_backup']);
    $path = $cmsRoot . '/' . $file;
    if (backup_is_managed_backup_zip($file) && is_file($path)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// ── Delete a backup file ────────────────────────────────
if (isset($_POST['del_backup'])) {
    if (!cms_verify_csrf_post()) {
        $msg     = 'Security check failed. Reload and try again.';
        $msgType = 'error';
    } else {
        $file = basename((string) $_POST['del_backup']);
        $path = $cmsRoot . '/' . $file;
        if (backup_is_managed_backup_zip($file) && is_file($path)) {
            @unlink($path);
            $msg     = 'Deleted ' . $file;
            $msgType = 'success';
        }
    }
}

// ── Export ──────────────────────────────────────────────
if (isset($_POST['do_export'])) {
    if (!cms_verify_csrf_post()) {
        $msg     = 'Security check failed. Reload and try again.';
        $msgType = 'error';
    } else {
    $stamp   = date('Y-m-d_H-i-s');
    $zipName = 'backup_' . $stamp . '.zip';
    $tmpPath = sys_get_temp_dir() . '/' . $zipName;

    $zip = new ZipArchive();
    if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        addFolderToZip($zip, $cmsRoot, $cmsRoot);
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($tmpPath));
        readfile($tmpPath);
        unlink($tmpPath);
        exit;
    }
    $msg     = 'Failed to create ZIP archive.';
    $msgType = 'error';
    }
}

// ── Import ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_zip']) && is_uploaded_file($_FILES['import_zip']['tmp_name'])) {
    if (!cms_verify_csrf_post()) {
        $msg     = 'Security check failed. Reload and try again.';
        $msgType = 'error';
    } else {
    $err = $_FILES['import_zip']['error'] ?? UPLOAD_ERR_NO_FILE;
    $ext = strtolower(pathinfo($_FILES['import_zip']['name'], PATHINFO_EXTENSION));

    if ($err !== UPLOAD_ERR_OK) {
        $msg     = 'Upload error (code ' . $err . ').';
        $msgType = 'error';
    } elseif ($ext !== 'zip') {
        $msg     = 'Only .zip files are accepted.';
        $msgType = 'error';
    } else {
        $backupName = 'import_backup_' . date('Y-m-d_H-i-s') . '.zip';
        $backupPath = $cmsRoot . '/' . $backupName;
        $bak = new ZipArchive();
        if ($bak->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            addFolderToZip($bak, $cmsRoot, $cmsRoot);
            $bak->close();
        } else {
            $backupName = '';
        }

        $zip = new ZipArchive();
        if ($zip->open($_FILES['import_zip']['tmp_name']) === true) {
            $skip = $backupName ? [$backupName] : [];
            $delIt = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cmsRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($delIt as $item) {
                if (in_array(basename($item->getPathname()), $skip, true)) continue;
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
            $zip->extractTo($cmsRoot);
            $zip->close();
            $msg     = 'All existing files removed and ZIP extracted to project root.';
            if ($backupName) $msg .= ' Backup saved as ' . $backupName;
            $msgType = 'success';
        } else {
            $msg     = 'Could not open uploaded ZIP.';
            $msgType = 'error';
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1d2327">
    <title>Backup — CMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="<?php echo cms_url('admin_style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="wp-admin-skin<?php echo cms_is_maintenance_mode() ? ' admin-public-maintenance' : ''; ?>">
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
            <?php cms_render_admin_sidebar_nav(['mode' => 'fullpage', 'active' => 'backup']); ?>

            <div class="wp-admin-main">
                <div class="wp-admin-toolbar">
                    <div class="wp-admin-toolbar-title">
                        <i class="fas fa-cloud-download-alt" aria-hidden="true"></i>
                        <span>Backup</span>
                    </div>
                </div>

                <div class="wp-admin-scroll">
                    <div class="wrap">

                        <?php if ($msg): ?>
                            <div class="notice notice-<?php echo $msgType === 'success' ? 'success' : 'error'; ?>">
                                <p><?php echo htmlspecialchars($msg); ?></p>
                            </div>
                        <?php endif; ?>

                        <div style="display:flex; flex-wrap:wrap; gap:16px;">

                            <!-- Export -->
                            <div class="postbox" style="flex:1; min-width:260px;">
                                <h2 class="postbox-header">Export</h2>
                                <div class="postbox-inner">
                                    <p style="margin:0 0 12px; font-size:13px; color:var(--ink3);">
                                        Download all project files and folders as a single <strong>.zip</strong> archive.
                                    </p>
                                    <form method="post">
                                        <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                        <button type="submit" name="do_export" class="button button-primary">
                                            <i class="fas fa-download" aria-hidden="true"></i> Download ZIP
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Import -->
                            <div class="postbox" style="flex:1; min-width:260px;">
                                <h2 class="postbox-header">Import</h2>
                                <div class="postbox-inner">
                                    <p style="margin:0 0 12px; font-size:13px; color:var(--ink3);">
                                        Upload a <strong>.zip</strong> file. All existing files will be removed and replaced with the ZIP contents.
                                    </p>
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                        <input type="file" name="import_zip" accept=".zip" required style="margin-bottom:10px;">
                                        <br>
                                        <button type="submit" class="button button-primary">
                                            <i class="fas fa-upload" aria-hidden="true"></i> Upload &amp; Extract
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </div>

                        <?php
                        $backups = [];
                        foreach (glob($cmsRoot . '/*.zip') as $f) {
                            $bn = basename($f);
                            if (!backup_is_managed_backup_zip($bn)) {
                                continue;
                            }
                            $backups[] = ['name' => $bn, 'size' => filesize($f), 'time' => filemtime($f)];
                        }
                        usort($backups, function ($a, $b) { return $b['time'] - $a['time']; });
                        ?>
                        <div class="postbox" style="margin-top:16px;">
                            <h2 class="postbox-header">Backup Files</h2>
                            <div class="postbox-inner" style="padding:0;">
                            <?php if ($backups): ?>
                                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                    <thead>
                                        <tr style="border-bottom:1px solid var(--rule);">
                                            <th style="text-align:left;padding:10px 14px;font-weight:600;color:var(--ink2);">File</th>
                                            <th style="text-align:left;padding:10px 14px;font-weight:600;color:var(--ink2);">Size</th>
                                            <th style="text-align:left;padding:10px 14px;font-weight:600;color:var(--ink2);">Date</th>
                                            <th style="text-align:right;padding:10px 14px;font-weight:600;color:var(--ink2);">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($backups as $b): ?>
                                        <tr style="border-bottom:1px solid var(--rule);">
                                            <td style="padding:10px 14px;color:var(--ink);font-weight:500;"><?php echo htmlspecialchars($b['name']); ?></td>
                                            <td style="padding:10px 14px;color:var(--mid);"><?php echo round($b['size'] / 1024 / 1024, 2); ?> MB</td>
                                            <td style="padding:10px 14px;color:var(--mid);"><?php echo date('Y-m-d H:i', $b['time']); ?></td>
                                            <td style="padding:10px 14px;text-align:right;">
                                                <a href="?dl_backup=<?php echo urlencode($b['name']); ?>" style="color:var(--ink);font-weight:600;text-decoration:none;margin-right:12px;" title="Download"><i class="fas fa-download"></i></a>
                                                <form method="post" style="display:inline;margin:0;" onsubmit="return confirm('Delete this backup?');">
                                                    <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                                    <input type="hidden" name="del_backup" value="<?php echo htmlspecialchars($b['name']); ?>">
                                                    <button type="submit" style="background:none;border:none;padding:0;color:var(--red);font-weight:600;cursor:pointer;" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="padding:16px;font-size:13px;color:var(--mid);margin:0;">No backup snapshots yet. Listed files are only automatic snapshots (<code>import_backup_*.zip</code>, <code>full_system_backup_*.zip</code>, <code>hard_restore_backup_*.zip</code>, or export-style <code>backup_YYYY-mm-dd_H-ii-ss.zip</code>). Other <code>.zip</code> files in the project (for example from an import) are not shown here.</p>
                            <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
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
