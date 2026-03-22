<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - <?php echo $brand; ?></title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php getHeader('Services'); ?>

    <main class="section">
        <h1>Our <span class="gradient-text">Services</span></h1>
        <p class="subtitle">Elevating digital footprints with PHP-powered technology and 3D design.</p>
        <div class="grid">
            <div class="card">
                <h3>3D WebGL Design</h3>
                <p>Custom 3D environments, interactive product displays, and immersive landing pages.</p>
            </div>
            <div class="card">
                <h3>Algorithm SEO</h3>
                <p>Proprietary search optimization strategies designed for modern AI algorithms.</p>
            </div>
            <div class="card">
                <h3>PHP Development</h3>
                <p>Blazing fast server-side logic using flat-file storage for maximum speed.</p>
            </div>
            <div class="card">
                <h3>GitHub Integration</h3>
                <p>Full CI/CD pipeline setup for your PHP apps and multiple branch management.</p>
            </div>
            <div class="card">
                <h3>Cyber Security</h3>
                <p>SQL-less backend architecture with zero database attack surface.</p>
            </div>
            <div class="card">
                <h3>UI/UX Mastery</h3>
                <p>Premium glassmorphic interfaces designed for the ultimate user experience.</p>
            </div>
        </div>
    </main>
    <script src="main.js"></script>
</body>
</html>
