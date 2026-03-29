<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cms_core.php';
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: viv-admin.php');
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
    <link rel="icon" type="image/svg+xml" href="<?php echo cms_generate_text_favicon_svg(cms_brand()); ?>">
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
            border-radius: 0;
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
            border-radius: 0;
            margin-bottom: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(0, 242, 254, 0.1);
            color: #00f2fe;
            border-radius: 0;
            font-size: 12px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php getPanel(); // Include the hidden secret panel too ?>
    <?php getHeader('Admin'); ?>

    <main class="section">
        <h1>Admin <span class="gradient-text">Dashboard</span></h1>
        <p class="subtitle">Quick access to all your CMS pages and system settings.</p>

        <div style="margin-top: 50px; text-align: center;">
            <a href="viv-admin.php" class="panel-btn" style="display: inline-block; text-decoration: none;">Open Page Editor</a>
        </div>
        
        <p style="margin-top: 60px; color: #555; font-size: 14px; text-align: center;">Hint: Use your secret code to open the quick-navigation panel from anywhere on the site.</p>
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
