<?php
include 'config.php';
include 'cms_core.php';
require_once __DIR__ . '/admin_menu.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin.php");
    exit;
}

$csrf = cms_csrf_token();

$uploadDir = __DIR__ . '/uploads';
$maxBytes = 8 * 1024 * 1024;
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'webm', 'mp3', 'zip'];

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media_file']) && is_uploaded_file($_FILES['media_file']['tmp_name'])) {
    if (!cms_verify_csrf_post()) {
        header('Location: media_manager.php?upload_err=1');
        exit;
    }
    $err = $_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_OK) {
        $size = (int) $_FILES['media_file']['size'];
        $orig = basename((string) $_FILES['media_file']['name']);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($size > 0 && $size <= $maxBytes && $ext !== '' && in_array($ext, $allowedExt, true)) {
            $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
            $safe = trim($safe, '._-') ?: 'file';
            $destName = $safe . '_' . substr(sha1((string) microtime(true)), 0, 8) . '.' . $ext;
            $destPath = $uploadDir . '/' . $destName;
            if (move_uploaded_file($_FILES['media_file']['tmp_name'], $destPath)) {
                header('Location: media_manager.php?uploaded=1');
                exit;
            }
        }
    }
    header('Location: media_manager.php?upload_err=1');
    exit;
}

function mediaLibraryIsImage(string $ext): bool
{
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'], true);
}

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 1) . ' MB';
}

function scanUploads(string $dir): array
{
    $items = [];
    if (!is_dir($dir)) {
        return $items;
    }
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $path = $dir . '/' . $f;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $items[] = [
            'name' => $f,
            'url' => cms_url('uploads/' . rawurlencode($f)),
            'mtime' => filemtime($path),
            'size' => filesize($path),
            'is_image' => mediaLibraryIsImage($ext),
            'ext' => $ext,
        ];
    }
    usort($items, static function ($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });
    return $items;
}

$items = scanUploads($uploadDir);
$sysVer = getSystemVersion();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1d2327">
    <title>Media ‹ Library — CMS</title>
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
            <?php cms_render_admin_sidebar_nav(['mode' => 'fullpage', 'active' => 'media']); ?>

            <div class="wp-admin-main">
                <div class="wp-admin-toolbar">
                    <div class="wp-admin-toolbar-title">
                        <i class="fas fa-camera-retro" aria-hidden="true"></i>
                        <span>Media Library</span>
                    </div>
                </div>

                <div class="wp-admin-scroll">
                    <div class="wrap">
                        <div class="media-page-header">
                            <div class="media-page-title-block">
                                <h1 class="screen-reader-text">Media library</h1>
                                <form class="media-upload-form" method="post" enctype="multipart/form-data" id="media-upload-form">
                                    <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="file" name="media_file" id="media_file" accept="image/*,.pdf,.zip,.mp4,.webm,.mp3,.svg" onchange="if(this.files.length)this.form.submit();">
                                    <label for="media_file" class="button button-primary page-title-action">Add media</label>
                                </form>
                            </div>
                            <div class="media-views" role="toolbar" aria-label="Attachment view mode">
                                <button type="button" class="media-view-btn is-active" data-view="grid" id="media-view-grid" title="Grid view" aria-pressed="true">
                                    <span class="screen-reader-text">Grid view</span>
                                    <i class="fas fa-th" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="media-view-btn" data-view="list" id="media-view-list" title="List view" aria-pressed="false">
                                    <span class="screen-reader-text">List view</span>
                                    <i class="fas fa-list-ul" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <hr class="wp-header-end">

                        <?php if (!empty($_GET['uploaded'])): ?>
                            <div class="notice notice-success is-dismissible"><p>File uploaded.</p></div>
                        <?php elseif (!empty($_GET['upload_err'])): ?>
                            <div class="notice notice-error"><p>Upload failed. Check file type (images, PDF, ZIP, audio, video) and size (max 8&nbsp;MB).</p></div>
                        <?php endif; ?>

                        <div class="postbox media-library view-grid" id="media-library">
                            <h2 class="postbox-header">Uploads</h2>
                            <div class="postbox-inner">
                                <div class="media-toolbar-inner">
                                    <p class="description" style="margin:0; max-width:520px;">Manage files in the <code>uploads</code> folder. Switch between grid and list like WordPress.</p>
                                    <span class="media-count"><strong><?php echo count($items); ?></strong> <?php echo count($items) === 1 ? 'item' : 'items'; ?></span>
                                </div>

                                <?php if (count($items) === 0): ?>
                                    <div class="media-empty">
                                        <span class="dashicons-placeholder" aria-hidden="true"><i class="fas fa-images"></i></span>
                                        No items found in the library. Use <strong>Add media</strong> to upload.
                                    </div>
                                <?php else: ?>
                                    <div class="media-grid-wrap">
                                        <ul class="media-grid" role="list">
                                            <?php foreach ($items as $it): ?>
                                                <li class="media-grid-item">
                                                    <a href="<?php echo htmlspecialchars($it['url']); ?>" target="_blank" rel="noopener" class="media-grid-card">
                                                        <span class="media-grid-thumb">
                                                            <?php if ($it['is_image']): ?>
                                                                <img src="<?php echo htmlspecialchars($it['url']); ?>" alt="" loading="lazy" width="280" height="280">
                                                            <?php else: ?>
                                                                <span class="media-grid-icon" aria-hidden="true"><i class="fas fa-file"></i></span>
                                                            <?php endif; ?>
                                                        </span>
                                                        <span class="media-grid-caption"><?php echo htmlspecialchars($it['name']); ?></span>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <div class="media-list-wrap">
                                        <table class="media-list-table">
                                            <thead>
                                                <tr>
                                                    <th class="col-thumb" scope="col">File</th>
                                                    <th scope="col">Name</th>
                                                    <th scope="col">Uploaded</th>
                                                    <th scope="col">Size</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $it): ?>
                                                    <tr>
                                                        <td class="col-thumb">
                                                            <?php if ($it['is_image']): ?>
                                                                <a href="<?php echo htmlspecialchars($it['url']); ?>" target="_blank" rel="noopener"><img class="media-list-thumb" src="<?php echo htmlspecialchars($it['url']); ?>" alt=""></a>
                                                            <?php else: ?>
                                                                <span class="media-list-file-icon"><i class="fas fa-file" aria-hidden="true"></i></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a class="file-link" href="<?php echo htmlspecialchars($it['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($it['name']); ?></a>
                                                            <div style="font-size:10px; color:var(--mid); text-transform:uppercase; letter-spacing:0.1em; font-weight:600; margin-top:2px;"><?php echo htmlspecialchars($it['ext']); ?></div>
                                                        </td>
                                                        <td class="col-meta"><?php echo date('M j, Y g:i a', $it['mtime']); ?></td>
                                                        <td class="col-meta"><?php echo htmlspecialchars(formatBytes($it['size'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
        if (shell && toggle) {
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
        }
    })();
    (function () {
        var root = document.getElementById('media-library');
        if (!root) return;
        var key = 'agentic_media_view';
        var stored = localStorage.getItem(key);
        var view = stored === 'list' ? 'list' : 'grid';

        function applyView(v) {
            root.classList.remove('view-grid', 'view-list');
            root.classList.add(v === 'list' ? 'view-list' : 'view-grid');
            localStorage.setItem(key, v === 'list' ? 'list' : 'grid');
            document.querySelectorAll('.media-view-btn').forEach(function (btn) {
                var on = btn.getAttribute('data-view') === v;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        applyView(view);
        document.querySelectorAll('.media-view-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyView(btn.getAttribute('data-view'));
            });
        });
    })();
    </script>
</body>
</html>
