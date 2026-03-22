<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - <?php echo $brand; ?></title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php getHeader('Home'); ?>

    <main class="section">
        <h1><span class="gradient-text">Future-Ready</span><br>Web Design & SEO</h1>
        <p class="subtitle">Experience the power of immersive 3D and no-SQL PHP backend architecture.</p>
        
        <div class="grid">
            <div class="card">
                <h3>3D Immersive</h3>
                <p>Interactive WebGL environments delivered via fast PHP server-side rendering.</p>
            </div>
            <div class="card">
                <h3>PHP SEO</h3>
                <p>Data-driven server-side optimization to outrank the competition.</p>
            </div>
            <div class="card">
                <h3>Speed & Scale</h3>
                <p>Flat-file storage and GitHub CI/CD integration for unmatched performance.</p>
            </div>
        </div>
    </main>
    <script src="main.js"></script>
</body>
</html>
