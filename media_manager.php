<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cms_core.php';
require_once __DIR__ . '/admin_menu.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: viv-admin.php");
    exit;
}
$menuUserRecord = cms_current_user_record();
$allowedMenuKeys = cms_user_allowed_menu_keys($menuUserRecord);
if (!cms_user_may_access_menu_key($menuUserRecord, 'media')) {
    header('Location: viv-admin.php?tab=' . rawurlencode($allowedMenuKeys[0] ?? 'pages'));
    exit;
}

$csrf = cms_csrf_token();

$uploadDir = __DIR__ . '/uploads';
$maxBytes = 100 * 1024 * 1024; // 100 MB
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'webm', 'mp3', 'zip'];

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Special check for PHP's post_max_size limit (happens before script starts)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    header('Location: media_manager.php?upload_err=php_post_max');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media_file'])) {
    $isAjax = isset($_POST['ajax_upload']);
    if (!cms_verify_csrf_post()) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'csrf']);
            exit;
        }
        header('Location: media_manager.php?upload_err=csrf');
        exit;
    }

    $uploadedFiles = $_FILES['media_file'];
    // Handle both single and multiple file uploads (normalize to array)
    $fileNames = (array) ($uploadedFiles['name'] ?? []);
    $fileTmpNames = (array) ($uploadedFiles['tmp_name'] ?? []);
    $fileErrors = (array) ($uploadedFiles['error'] ?? []);
    $fileSizes = (array) ($uploadedFiles['size'] ?? []);

    $uploadedCount = 0;
    $lastErr = '';

    for ($i = 0; $i < count($fileNames); $i++) {
        $err = $fileErrors[$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) {
            if ($err !== UPLOAD_ERR_NO_FILE) {
                $lastErr = 'php_' . $err;
            }
            continue;
        }
        
        $tmp = $fileTmpNames[$i];
        if (!is_uploaded_file($tmp)) continue;

        $size = (int)$fileSizes[$i];
        $orig = basename((string)$fileNames[$i]);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        
        $imageMimes = ['image/jpeg', 'image/png', 'image/x-png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $mimeMap = [
            'jpg' => $imageMimes, 'jpeg' => $imageMimes, 'png' => $imageMimes, 'gif' => $imageMimes,
            'webp' => $imageMimes, 'svg' => $imageMimes,
            'pdf' => ['application/pdf'],
            'mp4' => ['video/mp4'], 'webm' => ['video/webm'],
            'mp3' => ['audio/mpeg'], 'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        ];

        $valid = ($size > 0 && $size <= $maxBytes && $ext !== '' && in_array($ext, $allowedExt, true));
        if ($valid) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($tmp);
            $validMimes = $mimeMap[$ext] ?? [];
            if ($detectedMime !== false && ($validMimes === [] || in_array($detectedMime, $validMimes, true))) {
                // Auto-convert formats if mismatch (e.g. JPG content renamed to .png)
                if ($detectedMime === 'image/jpeg' && $ext === 'png') {
                    $img = @imagecreatefromjpeg($tmp);
                    if ($img) { imagepng($img, $tmp); imagedestroy($img); $size = filesize($tmp); }
                } elseif ($detectedMime === 'image/png' && ($ext === 'jpg' || $ext === 'jpeg')) {
                    $img = @imagecreatefrompng($tmp);
                    if ($img) { imagejpeg($img, $tmp); imagedestroy($img); $size = filesize($tmp); }
                } elseif ($detectedMime === 'image/webp' && ($ext === 'png')) {
                    $img = @imagecreatefromwebp($tmp);
                    if ($img) { imagepng($img, $tmp); imagedestroy($img); $size = filesize($tmp); }
                }

                if ($ext === 'svg') {
                    $svgContent = file_get_contents($tmp);
                    if (preg_match('/<\s*script|on\w+\s*=|javascript\s*:/i', $svgContent)) {
                        $valid = false;
                    }
                }
                if ($valid && preg_match('/\.php/i', $orig)) {
                    $valid = false;
                }
                if ($valid) {
                    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME));
                    $safe = trim($safe, '._-') ?: 'file';
                    
                    $destName = $safe . '.' . $ext;
                    if (file_exists($uploadDir . '/' . $destName)) {
                        $countSuffix = 1;
                        while (file_exists($uploadDir . '/' . $safe . '-' . $countSuffix . '.' . $ext)) {
                            $countSuffix++;
                        }
                        $destName = $safe . '-' . $countSuffix . '.' . $ext;
                    }
                    
                    $destPath = $uploadDir . '/' . $destName;
                    if (move_uploaded_file($tmp, $destPath)) {
                        $uploadedCount++;
                    }
                }
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $uploadedCount > 0, 'count' => $uploadedCount, 'error' => $lastErr ?: 'validation']);
        exit;
    }
    if ($uploadedCount > 0) {
        header('Location: media_manager.php?uploaded=' . $uploadedCount);
        exit;
    }
    header('Location: media_manager.php?upload_err=' . ($lastErr ?: 'validation'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_media']) || isset($_POST['bulk_delete']))) {
    if (!cms_verify_csrf_post()) {
        header('Location: media_manager.php?delete_err=csrf');
        exit;
    }
    
    $filesToRemove = [];
    if (isset($_POST['bulk_delete'])) {
        if (is_array($_POST['media_items'] ?? null)) {
            foreach ($_POST['media_items'] as $item) {
                $filesToRemove[] = basename((string)$item);
            }
        }
    } else {
        $filesToRemove[] = basename((string) ($_POST['media_basename'] ?? ''));
    }

    $deletedCount = 0;
    foreach ($filesToRemove as $f) {
        if ($f !== '') {
            $fullPath = $uploadDir . '/' . $f;
            if (is_file($fullPath)) {
                if (@unlink($fullPath)) { $deletedCount++; }
            }
        }
    }

    if ($deletedCount > 0) {
        header('Location: media_manager.php?deleted=' . $deletedCount);
        exit;
    }
    header('Location: media_manager.php?delete_err=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_one'])) {
    if (!cms_verify_csrf_get()) {
        header('Location: media_manager.php?delete_err=csrf');
        exit;
    }
    $f = basename((string)$_GET['delete_one']);
    if ($f !== '') {
        $fullPath = $uploadDir . '/' . $f;
        if (is_file($fullPath)) {
            if (@unlink($fullPath)) {
                header('Location: media_manager.php?deleted=1');
                exit;
            }
        }
    }
    header('Location: media_manager.php?delete_err=1');
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
        if ($f === '.' || $f === '..' || $f === 'index.php' || $f === '.htaccess') {
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
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="<?php echo cms_url('admin_style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="<?php echo cms_generate_text_favicon_svg(cms_brand()); ?>">
    <style>
        .media-grid-item-inner { position: relative; }
        .media-copy-btn {
            position: absolute;
            top: 5px;
            right: 35px;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.9);
            border: 1px solid var(--rule);
            color: var(--mid);
            border-radius: 0;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            z-index: 10;
            opacity: 0;
        }
        .media-grid-item:hover .media-copy-btn { opacity: 1; }
        .media-copy-btn:hover { background: var(--wh); color: var(--accent); border-color: var(--accent); }
        
        .media-copy-btn--text {
            position: static;
            background: none;
            border: none;
            color: var(--accent);
            padding: 0;
            margin-right: 12px;
            width: auto;
            height: auto;
            font-size: 11px;
            font-weight: 600;
            opacity: 1;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .media-copy-btn--text:hover { text-decoration: underline; background: none; }
        
        /* Bulk selection styles */
        .media-grid-item.is-selected .media-grid-item-inner { outline: 3px solid var(--accent); outline-offset: -3px; }
        .media-list-table tr.is-selected { background: rgba(79, 172, 254, 0.05); }
        .media-bulk-checkbox { cursor: pointer; width: 16px; height: 16px; accent-color: var(--accent); }
    </style>
</head>

<body class="wp-admin-skin<?php echo cms_is_maintenance_mode() ? ' admin-public-maintenance' : ''; ?>">
    <div class="wp-admin-shell">
        <header class="wp-admin-bar" role="banner">
            <div class="wp-admin-bar-row">
                <div class="wp-admin-bar-site">
                    <button type="button" class="wp-menu-toggle" id="wp-menu-toggle" aria-expanded="false"
                        aria-controls="wp-admin-menu" aria-label="Open menu">
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
                    <a href="<?php echo cms_escape(cms_home_url()); ?>" target="_blank" rel="noopener"
                        class="wp-bar-visit"><i class="fas fa-external-link-alt" aria-hidden="true"></i><span>View
                            site</span></a>
                    <span class="wp-bar-user">
                        <span class="wp-bar-avatar" aria-hidden="true">A</span>
                        <span class="wp-bar-greet">Howdy, <strong>admin</strong></span>
                    </span>
                </div>
            </div>
        </header>

        <div class="wp-admin-frame">
            <?php cms_render_admin_sidebar_nav(['mode' => 'fullpage', 'active' => 'media', 'allowed_keys' => $allowedMenuKeys]); ?>

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
                                <form class="media-upload-form" method="post" enctype="multipart/form-data"
                                    id="media-upload-form">
                                    <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <input type="file" name="media_file[]" id="media_file"
                                        accept="image/*,.pdf,.zip,.mp4,.webm,.mp3,.svg"
                                        onchange="if(this.files.length)this.form.submit();" multiple>
                                    <label for="media_file" class="button button-primary page-title-action">Add
                                        media</label>
                                </form>
                            </div>
                            <div class="media-views" role="toolbar" aria-label="Attachment view mode">
                                <button type="button" class="media-view-btn is-active" data-view="grid"
                                    id="media-view-grid" title="Grid view" aria-pressed="true">
                                    <span class="screen-reader-text">Grid view</span>
                                    <i class="fas fa-th" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="media-view-btn" data-view="list" id="media-view-list"
                                    title="List view" aria-pressed="false">
                                    <span class="screen-reader-text">List view</span>
                                    <i class="fas fa-list-ul" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <hr class="wp-header-end">

                        <?php
if (!empty($_GET['uploaded'])) {
    $c = (int)$_GET['uploaded'];
    echo '<div class="notice notice-success is-dismissible"><p>' . $c . ' ' . ($c === 1 ? 'file' : 'files') . ' uploaded.</p></div>';
}
                        if (!empty($_GET['upload_err'])) {
                            $ue = (string) $_GET['upload_err'];
                            $ueMsg = 'Upload failed. Check file type (images, PDF, ZIP, audio, video) and size (max 100 MB).';
                            if ($ue === 'csrf') {
                                $ueMsg = 'Security session expired. Refresh the page and try again.';
                            } elseif ($ue === 'php_1' || $ue === 'php_2') {
                                $ueMsg = 'File is too large for the server. Check PHP upload_max_filesize and post_max_size.';
                            } elseif ($ue === 'php_post_max') {
                                $ueMsg = 'Upload failed: POST limit exceeded. The total size of selected files is too large for the server configuration.';
                            }
                            echo '<div class="notice notice-error"><p>' . htmlspecialchars($ueMsg) . ' (' . htmlspecialchars($ue) . ')</p></div>';
                        }
if (!empty($_GET['deleted'])) {
    $count = (int)$_GET['deleted'];
    echo '<div class="notice notice-success is-dismissible"><p>' . $count . ' ' . ($count === 1 ? 'file' : 'files') . ' deleted.</p></div>';
}
if (!empty($_GET['delete_err'])) {
    $errNotice = ($_GET['delete_err'] === 'csrf' ? 'Security check failed. Try again.' : 'Could not delete that file.');
    echo '<div class="notice notice-error"><p>' . htmlspecialchars($errNotice) . '</p></div>';
}
?>

                        <div class="postbox media-library view-grid" id="media-library">
                            <h2 class="postbox-header">Uploads</h2>
                            <div class="postbox-inner">
                                    <form method="post" id="media-bulk-form">
                                    <input type="hidden" name="cms_csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                                    <div class="media-bulk-actions" style="display:none;align-items:center;gap:12px;" id="media-bulk-actions">
                                        <span style="font-size:12px;color:var(--mid);font-weight:600;"><span id="bulk-select-count">0</span> selected</span>
                                        <button type="submit" name="bulk_delete" class="button button-secondary" style="color:var(--red);border-color:rgba(193,18,31,0.2);background:rgba(193,18,31,0.03);" onclick="return confirm('Delete selected files?');">
                                            <i class="fas fa-trash-alt" aria-hidden="true" style="margin-right:6px;"></i> Bulk Delete
                                        </button>
                                    </div>
                                    <div class="media-toolbar-inner" id="media-default-toolbar">
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
                                                <div class="media-grid-item-inner">
                                                    <label class="media-item-checkbox-wrapper" style="position:absolute;top:10px;left:10px;z-index:20;background:rgba(255,255,255,0.9);width:22px;height:22px;border-radius:0;border:1px solid rgba(0,0,0,0.1);display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                                                        <input type="checkbox" name="media_items[]" value="<?php echo htmlspecialchars($it['name']); ?>" class="media-bulk-checkbox" onchange="updateBulkActions()">
                                                    </label>
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
                                                    <button type="button" class="media-copy-btn" title="Copy public URL" onclick="cmsCopyMediaUrl('<?php echo htmlspecialchars($it['url'], ENT_QUOTES, 'UTF-8'); ?>', this)"><i class="fas fa-link" aria-hidden="true"></i></button>
                                                    <button type="button" class="media-delete-btn" title="Delete file" onclick="if(confirm('Delete this file?')){ window.location.href='media_manager.php?delete_one=<?php echo urlencode($it['name']); ?>&cms_csrf=<?php echo $csrf; ?>'; }"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
                                                </div>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>

                                    <div class="media-list-wrap">
                                        <table class="media-list-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:40px;text-align:center;"><input type="checkbox" id="select-all-media" onchange="toggleSelectAllMedia(this)"></th>
                                                    <th class="col-thumb" scope="col">File</th>
                                                    <th scope="col">Name</th>
                                                    <th scope="col">Uploaded</th>
                                                    <th scope="col">Size</th>
                                                    <th class="col-actions" scope="col"><span class="screen-reader-text">Actions</span></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $it): ?>
                                                <tr>
                                                    <td style="text-align:center;"><input type="checkbox" name="media_items[]" value="<?php echo htmlspecialchars($it['name']); ?>" class="media-bulk-checkbox" onchange="updateBulkActions()"></td>
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
                                                    <td class="col-actions">
                                                        <button type="button" class="media-copy-btn media-copy-btn--text" title="Copy public URL" onclick="cmsCopyMediaUrl('<?php echo htmlspecialchars($it['url'], ENT_QUOTES, 'UTF-8'); ?>', this)">Copy URL</button>
                                                        <button type="button" class="media-delete-btn media-delete-btn--text" onclick="if(confirm('Delete this file?')){ window.location.href='media_manager.php?delete_one=<?php echo urlencode($it['name']); ?>&cms_csrf=<?php echo $csrf; ?>'; }">Delete</button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            window.cmsCopyMediaUrl = function (url, btn) {
                var inp = document.createElement('textarea');
                inp.value = url;
                inp.style.position = 'fixed'; inp.style.opacity = '0';
                document.body.appendChild(inp); inp.select();
                try {
                    document.execCommand('copy');
                    var old = btn.innerHTML;
                    var isText = btn.classList.contains('media-copy-btn--text');
                    btn.innerHTML = isText ? 'Copied!' : '<i class="fas fa-check"></i>';
                    setTimeout(function () { btn.innerHTML = old; }, 2000);
                } catch (err) { }
                document.body.removeChild(inp);
            };

            var shell = document.querySelector('.wp-admin-shell');
            var toggle = document.getElementById('wp-menu-toggle');
            var backdrop = document.querySelector('.wp-admin-menu-backdrop');
            if (shell && toggle) {
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

        function toggleSelectAllMedia(master) {
            const boxes = document.querySelectorAll('.media-bulk-checkbox');
            boxes.forEach(cb => cb.checked = master.checked);
            updateBulkActions();
        }

        function updateBulkActions() {
            const boxes = document.querySelectorAll('.media-bulk-checkbox');
            const selected = Array.from(boxes).filter(cb => cb.checked);
            const bulkBar = document.getElementById('media-bulk-actions');
            const defaultBar = document.getElementById('media-default-toolbar');
            const countEl = document.getElementById('bulk-select-count');
            
            // Update visual state
            boxes.forEach(cb => {
                const row = cb.closest('tr');
                const card = cb.closest('.media-grid-item');
                if (row) row.classList.toggle('is-selected', cb.checked);
                if (card) card.classList.toggle('is-selected', cb.checked);
            });

            if (selected.length > 0) {
                bulkBar.style.display = 'flex';
                defaultBar.style.display = 'none';
                countEl.textContent = selected.length;
            } else {
                bulkBar.style.display = 'none';
                defaultBar.style.display = 'flex';
                const master = document.getElementById('select-all-media');
                if (master) master.checked = false;
            }
        }
    </script>
</body>

</html>
