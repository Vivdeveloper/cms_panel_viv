<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cms_core.php';
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars(cms_brand()); ?></title>
    <link rel="stylesheet" href="<?php echo cms_url('public_style.css'); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        .admin-grid {
            margin-top: 30px;
            width: 100%;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .admin-card {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.1);
            padding: 25px;
            border-radius: 15px;
            text-align: left;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
        }
        .admin-card h4 {
            color: #4facfe;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .admin-input {
            background: #f8fafc;
            border: 1px solid rgba(15, 23, 42, 0.12);
            color: #0f172a;
            padding: 10px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(0, 242, 254, 0.1);
            color: #00f2fe;
            border-radius: 5px;
            font-size: 12px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div id="canvas-container"></div>
    <?php getPanel(); // Include the hidden secret panel too ?>
    <?php getHeader('Admin'); ?>

    <main class="section">
        <h1>Admin <span class="gradient-text">Dashboard</span></h1>
        <p class="subtitle">Full-screen management for your GitHub & FTP deployments.</p>

        <div class="admin-grid" style="grid-template-columns: 1fr; max-width: 600px; margin: 30px auto;">
            <!-- GitHub Card -->
            <div class="admin-card">
                <span class="status-badge">Live Sync Active</span>
                <h4>Branch Selection</h4>
                <select class="admin-input" id="admin-branch">
                    <option value="main">main</option>
                    <option value="master">master</option>
                    <option value="dev">dev</option>
                    <option value="staging">staging</option>
                </select>
                <button class="panel-btn" onclick="saveSettings()">Apply Dynamic Update</button>
            </div>
        </div>
        
        <p style="margin-top: 40px; color: #555; font-size: 14px;">Hint: You can open the secret quick-panel from any page with the same shortcut you use for the admin login.</p>
    </main>

    <script src="main.js"></script>
    <script>
        (function () {
            var ab = document.getElementById('admin-branch');
            var bs = document.getElementById('branch-select');
            if (ab && bs) {
                ab.addEventListener('change', function (e) { bs.value = e.target.value; });
            }
        })();
    </script>
</body>
</html>
