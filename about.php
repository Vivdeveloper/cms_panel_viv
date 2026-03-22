<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - <?php echo $brand; ?></title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php getHeader('About'); ?>

    <main class="section">
        <h1>About <span class="gradient-text">Creativ3</span></h1>
        <p class="subtitle">A collective of developers and architects redefining the PHP digital landscape.</p>
        <div class="grid">
            <div class="card">
                <h3>Our PHP Mission</h3>
                <p>To deliver stunning 3D experiences that are blazing fast with PHP backends.</p>
            </div>
            <div class="card">
                <h3>Flat-File Tech</h3>
                <p>We leverage PHP and no-SQL methodologies to create unhackable, high-performance websites.</p>
            </div>
            <div class="card">
                <h3>Global Branding</h3>
                <p>Scaling brands from local markets to international search visibility using PHP-driven SEO.</p>
            </div>
        </div>
    </main>
    <script src="main.js"></script>
</body>
</html>
