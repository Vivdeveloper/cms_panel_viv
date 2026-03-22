<?php 
include 'config.php'; 
include 'cms_core.php';

// Fetch home page early
$pages = getAllCMSPages();
$homePage = null;
foreach ($pages as $p) { if ($p['is_home'] ?? false) { $homePage = $p; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - <?php echo $brand; ?></title>
    <link rel="stylesheet" href="<?php echo cms_url('public_style.css'); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php getHeader('Home'); ?>

    <?php
    if ($homePage): ?>
        <style><?php echo $homePage['css']; ?></style>
        <main class="dynamic-container section">
            <?php echo $homePage['html']; ?>
        </main>
    <?php else: ?>
        <main class="section" style="text-align:center; padding-top:100px;">
            <div style="background:rgba(255,255,255,0.03); border:1px dashed rgba(255,255,255,0.1); padding: 50px; border-radius: 20px; max-width: 600px; margin: 0 auto;">
                <h2 style="color: #4facfe; margin-bottom: 15px;">Dafult design page not found</h2>
                <p style="color: #666; font-size: 16px;">Please select your dynamic design from the Admin Panel and mark it as 'Home'.</p>
                <div style="margin-top:25px;">
                    <a href="admin.php" style="background: #4facfe; color: black; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size:14px;">Open Admin Panel</a>
                </div>
            </div>
        </main>
    <?php endif; ?>

    <script src="main.js"></script>
</body>
</html>
