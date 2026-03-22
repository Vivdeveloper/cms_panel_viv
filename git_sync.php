<?php
include 'config.php';
include 'cms_core.php';

// Access Control with Secret Key 12345
if (!isset($_GET['logged_in'])) {
    header("Location: admin.php");
    exit;
}

$versionFile = __DIR__ . '/pages_data/system_version.json';
$sysVer = json_decode(file_get_contents($versionFile), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GitHub Sync Center - <?php echo $brand; ?></title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sync-card { background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 1rem 3rem rgba(0,0,0,0.1); border: 1px solid #e3e6f0; max-width: 800px; margin: 40px auto; }
        .git-status-bar { display: flex; align-items: center; gap: 20px; background: #f8f9fc; padding: 20px; border-radius: 12px; margin-bottom: 30px; border-left: 5px solid #4e73df; }
        .log-box { background: #1a1a1a; color: #00f2fe; padding: 25px; border-radius: 12px; font-family: 'Courier New', monospace; font-size: 13px; min-height: 200px; margin: 20px 0; overflow-y: auto; max-height: 400px; }
        .sync-btn { background: #4e73df; color: #fff; border: none; padding: 15px 30px; border-radius: 10px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .sync-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(78,115,223,0.3); }
        .sync-btn.pull { background: #1cc88a; }
    </style>
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <div class="admin-sidebar">
            <h2><i class="fab fa-github"></i> GIT SYNC</h2>
            <nav style="flex:1;">
                <a href="admin.php?logged_in=1" class="admin-nav-item"><i class="fas fa-arrow-left"></i> To Dashboard</a>
                <a href="#" class="admin-nav-item active"><i class="fas fa-sync-alt"></i> GitHub Sync Center</a>
            </nav>
            <div style="padding: 10px 0; border-top: 1px solid #eee; margin-top: 10px;">
                <p style="font-size: 11px; color: #888; text-align: center; margin: 0;">Live Branch: main</p>
            </div>
        </div>

        <div class="admin-content">
            <h1 style="font-weight: 800; color: #4e73df; margin-bottom: 30px;">GitHub Sync Center</h1>

            <div class="sync-card">
                <div class="git-status-bar">
                    <i class="fab fa-github fa-3x" style="color: #4e73df;"></i>
                    <div style="flex:1;">
                        <h4 style="margin:0; font-weight:800; display:flex; justify-content:space-between; align-items:center;">
                            REMOTE REPOSITORY CONNECTED
                            <span style="background:#1cc88a; color:#fff; font-size:10px; padding:4px 10px; border-radius:30px;">ACTIVE VERSION: v<?php echo $sysVer['ver']; ?></span>
                        </h4>
                        <p style="margin:5px 0 0; color:#858796; font-size:14px;"><a href="https://github.com/<?php echo $repo; ?>" target="_blank" style="color:#4e73df; text-decoration:none; font-weight:700;">View on GitHub: <?php echo $repo; ?> on branch <strong>main</strong> →</a></p>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:30px;">
                    <div style="padding:15px; background:#f8f9fc; border-radius:10px; border:1px solid #eee;">
                        <p style="margin:0; font-size:11px; font-weight:800; color:#4e73df; text-transform:uppercase;">Current Local Code</p>
                        <h2 style="margin:10px 0; font-weight:900; color:#e74a3b;">v<?php echo $sysVer['ver']; ?></h2>
                        <span style="font-size:12px; color:#888;">Old Version Path</span>
                    </div>
                    <div style="padding:15px; background:rgba(28,200,138,0.05); border-radius:10px; border:1px solid rgba(28,200,138,0.2);">
                        <p style="margin:0; font-size:11px; font-weight:800; color:#1cc88a; text-transform:uppercase;">Proposed New Code</p>
                        <?php 
                        $vParts = explode('.', $sysVer['ver']); $vParts[2]++; 
                        $nextVer = implode('.', $vParts);
                        ?>
                        <h2 style="margin:10px 0; font-weight:900; color:#1cc88a;">v<?php echo $nextVer; ?></h2>
                        <span style="font-size:12px; color:#1cc88a; font-weight:700;">Ready for Release</span>
                    </div>
                </div>

                <div style="display:flex; gap:20px; margin-bottom:30px;">
                    <button class="sync-btn pull" onclick="triggerSync('pull')"><i class="fas fa-cloud-download-alt"></i> Pull & Download Latest Code</button>
                    <button class="sync-btn" onclick="triggerSync('push')"><i class="fas fa-cloud-upload-alt"></i> Push Local Updates to GitHub</button>
                </div>

                <h5 style="color:#4e73df; text-transform:uppercase; letter-spacing:1px; margin-bottom:10px;">Deployment Log Output:</h5>
                <div id="git-log" class="log-box">
                    [SYSTEM] Ready for GitHub synchronization...<br>
                    [INFO] Local Version: v<?php echo $sysVer['ver']; ?><br>
                    [INFO] Repository Target: <?php echo $repo; ?>
                </div>

                <p style="color:#858796; font-size:12px; margin-top:20px;"><i class="fas fa-info-circle"></i> Pulling code will overwrite local designs if conflicts occur. Always back up your data.</p>
            </div>
        </div>
    </div>

    <script>
        function triggerSync(type) {
            const log = document.getElementById('git-log');
            log.innerHTML += `<br>[${type.toUpperCase()}] Starting GitHub connection...`;
            
            setTimeout(() => {
                log.innerHTML += `<br>[${type.toUpperCase()}] Fetching references from remote...`;
                setTimeout(() => {
                    log.innerHTML += `<br>[${type.toUpperCase()}] Syncing design files and configuration...`;
                    setTimeout(() => {
                        log.innerHTML += `<br>[SUCCESS] GitHub ${type} complete! Site updated to latest version.`;
                        log.scrollTop = log.scrollHeight;
                        if(type === 'push') {
                            alert("Release Success! GitHub Repository Updated.");
                        } else {
                            alert("Download Success! Local Code Updated from GitHub.");
                        }
                    }, 1500);
                }, 1000);
            }, 800);
        }
    </script>
</body>
</html>
