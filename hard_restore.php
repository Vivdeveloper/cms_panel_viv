<?php
/**
 * HARD RESTORE — Emergency Recovery (Secured)
 *
 * 100% standalone — no includes, no sessions, no external dependencies.
 * Password is stored as a bcrypt hash (never plain text).
 * Brute-force protection: locks out after 5 failed attempts for 15 minutes.
 * CSRF token on the upload form.
 *
 * To change the password, run in terminal:
 *   php -r "echo password_hash('yourNewPassword', PASSWORD_BCRYPT);"
 * Then replace the $HASH value below.
 */

$HASH = '$2y$12$nO2su0rGWwUDdsweKaaVqOD./HJA3cXZb1vrERF4fHJlreKgkuwF2';

$LOCKFILE     = sys_get_temp_dir() . '/hr_' . md5(__FILE__) . '.lock';
$MAX_ATTEMPTS = 5;
$LOCKOUT_SECS = 900;

$root = __DIR__;
$msg  = '';
$ok   = false;
$auth = false;
$locked = false;

function hr_get_attempts(): array {
    global $LOCKFILE;
    if (!file_exists($LOCKFILE)) return ['count' => 0, 'time' => 0];
    $d = json_decode((string) file_get_contents($LOCKFILE), true);
    return is_array($d) ? $d : ['count' => 0, 'time' => 0];
}

function hr_set_attempts(int $count): void {
    global $LOCKFILE;
    file_put_contents($LOCKFILE, json_encode(['count' => $count, 'time' => time()]));
}

function hr_clear_attempts(): void {
    global $LOCKFILE;
    if (file_exists($LOCKFILE)) @unlink($LOCKFILE);
}

$att = hr_get_attempts();
if ($att['count'] >= $MAX_ATTEMPTS && (time() - $att['time']) < $LOCKOUT_SECS) {
    $locked = true;
    $remaining = ceil(($LOCKOUT_SECS - (time() - $att['time'])) / 60);
    $msg = "Too many failed attempts. Locked for {$remaining} min.";
}

if ($att['count'] >= $MAX_ATTEMPTS && (time() - $att['time']) >= $LOCKOUT_SECS) {
    hr_clear_attempts();
}

$csrf = '';

if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pw'])) {
    if (password_verify($_POST['pw'], $HASH)) {
        $auth = true;
        hr_clear_attempts();
        $csrf = bin2hex(random_bytes(32));
    } else {
        $att = hr_get_attempts();
        hr_set_attempts($att['count'] + 1);
        $left = $MAX_ATTEMPTS - $att['count'] - 1;
        $msg = 'Wrong password.' . ($left > 0 ? " {$left} attempts left." : ' Account locked.');
        if ($left <= 0) $locked = true;
    }
}

if (!$locked && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf']) && isset($_POST['csrf_check'])) {
    if (hash_equals($_POST['csrf'], $_POST['csrf_check'])) {
        $auth = true;
        $csrf = $_POST['csrf'];
    }
}

function hr_add_folder(ZipArchive $zip, string $folder, string $base): void {
    $handle = opendir($folder);
    if (!$handle) return;
    while (($entry = readdir($handle)) !== false) {
        if ($entry === '.' || $entry === '..') continue;
        $fullPath = $folder . '/' . $entry;
        $relPath  = substr($fullPath, strlen($base) + 1);
        if (is_dir($fullPath)) {
            $zip->addEmptyDir($relPath);
            hr_add_folder($zip, $fullPath, $base);
        } elseif (is_file($fullPath)) {
            $zip->addFile($fullPath, $relPath);
        }
    }
    closedir($handle);
}

$backupFile = '';

if ($auth && isset($_FILES['restore_zip']) && is_uploaded_file($_FILES['restore_zip']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['restore_zip']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'zip') {
        $msg = 'Only .zip files accepted.';
    } else {
        $bak = new ZipArchive();
        $backupFile = $root . '/hard_restore_backup_' . date('Y-m-d_H-i-s') . '.zip';
        if ($bak->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            hr_add_folder($bak, $root, $root);
            $bak->close();
        } else {
            $backupFile = '';
        }

        $zip = new ZipArchive();
        if ($zip->open($_FILES['restore_zip']['tmp_name']) === true) {
            $skip = ['hard_restore.php'];
            if ($backupFile) $skip[] = basename($backupFile);
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) {
                $name = basename($item->getPathname());
                if (in_array($name, $skip, true)) continue;
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
            $zip->extractTo($root);
            $zip->close();
            $ok  = true;
            $msg = 'Restore complete. All files replaced from ZIP.';
            if ($backupFile) $msg .= ' Backup saved as ' . basename($backupFile);
        } else {
            $msg = 'Could not open ZIP file.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hard Restore</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Poppins',system-ui,sans-serif;background:#0a0a0a;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:420px;border:1px solid #222}
.card-head{background:#fff;color:#000;padding:18px 24px;text-align:center}
.card-head h1{font-size:15px;font-weight:700;letter-spacing:.02em}
.card-head p{font-size:11px;color:#666;margin-top:4px}
.card-body{padding:24px}
label{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#888;margin-bottom:6px}
input[type=password],input[type=file]{width:100%;padding:10px 12px;background:#111;border:1px solid #333;color:#fff;font-size:13px;font-family:inherit;margin-bottom:16px}
input[type=password]:focus,input[type=file]:focus{outline:none;border-color:#fff}
button{width:100%;padding:12px;background:#fff;color:#000;border:0;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .12s}
button:hover{background:#ddd}
button:disabled{background:#333;color:#666;cursor:not-allowed}
.msg{padding:12px 16px;margin-bottom:16px;font-size:13px;border:1px solid #333}
.msg.ok{border-color:#2d6a4f;color:#52b788}
.msg.err{border-color:#c1121f;color:#e5383b}
.shield{display:flex;gap:8px;padding:10px 14px;background:#111;border:1px solid #222;margin-bottom:16px;font-size:11px;color:#555;line-height:1.5}
.shield i{color:#2d6a4f;font-size:13px;flex-shrink:0;margin-top:2px}
.warn{font-size:11px;color:#555;margin-top:16px;line-height:1.6;text-align:center}
.warn strong{color:#888}
a.back{display:block;text-align:center;margin-top:14px;color:#555;font-size:12px;text-decoration:none}
a.back:hover{color:#fff}
</style>
</head>
<body>
<div class="card">
<div class="card-head">
    <h1>Hard Restore</h1>
    <p>Emergency recovery — no admin required</p>
</div>
<div class="card-body">

<?php if ($msg): ?>
    <div class="msg <?php echo $ok ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if ($ok): ?>
    <a href="admin.php" style="display:block;text-align:center;padding:12px;background:#fff;color:#000;font-weight:700;font-size:13px;text-decoration:none;">Open Admin Panel</a>

<?php elseif ($locked): ?>
    <div class="shield">
        <span style="color:#e5383b;">&#9888;</span>
        <span>Too many failed attempts. This page is temporarily locked. Try again later.</span>
    </div>

<?php elseif ($auth): ?>
    <div class="shield">
        <span>&#10003;</span>
        <span>Authenticated. Ready to restore.</span>
    </div>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="csrf_check" value="<?php echo htmlspecialchars($csrf); ?>">
        <label>Upload Backup ZIP</label>
        <input type="file" name="restore_zip" accept=".zip" required>
        <p style="font-size:12px;color:#666;margin-bottom:16px;">This will <strong style="color:#e5383b;">delete all existing files</strong> and replace them with the ZIP contents.</p>
        <button type="submit">Restore Now</button>
    </form>

<?php else: ?>
    <div class="shield">
        <span>&#128274;</span>
        <span>Enter recovery password to continue.</span>
    </div>
    <form method="post">
        <label>Recovery Password</label>
        <input type="password" name="pw" placeholder="Enter password" required autofocus>
        <button type="submit">Authenticate</button>
    </form>
<?php endif; ?>

    <p class="warn"><strong>Keep this file safe.</strong> It works even when the entire CMS is broken.</p>
    <a class="back" href="admin.php">Back to admin</a>
</div>
</div>
</body>
</html>
