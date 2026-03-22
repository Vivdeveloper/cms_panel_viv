<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo $brand; ?></title>
    <link rel="stylesheet" href="styles.css">
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
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 25px;
            border-radius: 15px;
            text-align: left;
        }
        .admin-card h4 {
            color: #4facfe;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .admin-input {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
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

        <div class="admin-grid">
            <!-- GitHub Card -->
            <div class="admin-card">
                <span class="status-badge">Connected</span>
                <h4>GitHub Integration</h4>
                <p style="font-size: 14px; color: #888; margin-bottom: 15px;">Target Repository: <strong><?php echo $repo; ?></strong></p>
                <select class="admin-input" id="admin-branch">
                    <option>main</option>
                    <option>master</option>
                    <option>dev</option>
                </select>
                <button class="panel-btn" onclick="saveSettings()">Force Sync Branch</button>
            </div>

            <!-- FTP Card -->
            <div class="admin-card">
                <span class="status-badge">Ready</span>
                <h4>FTP Continuous Deployment</h4>
                <input type="text" class="admin-input" id="admin-ftp-host" placeholder="FTP Host (e.g. ftp.site.com)">
                <input type="text" class="admin-input" id="admin-ftp-user" placeholder="FTP Username">
                <button class="panel-btn" style="background: linear-gradient(45deg, #f093fb, #f5576c); color: #fff;" onclick="deployToFTP()">🚀 Live Update FTP</button>
            </div>

            <!-- Design Control -->
            <div class="admin-card">
                <span class="status-badge">Fast Sync</span>
                <h4>Design Controls</h4>
                <p style="font-size: 14px; color: #888; margin-bottom: 20px;">Download your current design files (.php) for local editing.</p>
                <button class="panel-btn" onclick="downloadProject('zip')" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1);">
                    Download Project ZIP
                </button>
            </div>
        </div>
        
        <p style="margin-top: 40px; color: #555; font-size: 14px;">Hint: You can still open the secret quick-panel from any page by typing 12345.</p>
    </main>

    <script src="main.js"></script>
    <script>
        // Sync Admin Dashboard fields with secret panel fields
        document.getElementById('admin-branch').addEventListener('change', (e) => {
            document.getElementById('branch-select').value = e.target.value;
        });
        document.getElementById('admin-ftp-host').addEventListener('input', (e) => {
            document.getElementById('ftp-host').value = e.target.value;
        });
        document.getElementById('admin-ftp-user').addEventListener('input', (e) => {
            document.getElementById('ftp-user').value = e.target.value;
        });
    </script>
</body>
</html>
