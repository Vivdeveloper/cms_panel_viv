<?php
include 'config.php';
include 'cms_core.php';

$slug = $_GET['page'];
$dataFile = __DIR__ . '/pages_data/' . $slug . '.json';

if (!file_exists($dataFile)) {
    die("Page not found on server.");
}

$page = json_decode(file_get_contents($dataFile), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucwords($slug); ?> - <?php echo $brand; ?></title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <style>
        /* Shared Styles */
        body { background: #050505; color: #fff; }
        .dynamic-container { margin-top: 150px; padding: 20px; }
        
        /* User-Defined Custom CSS Injection */
        <?php echo $page['css']; ?>
    </style>
</head>
<body>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php getHeader(ucwords($slug)); ?>

    <!-- User-Defined Custom HTML Injection -->
    <main class="dynamic-container section">
        <?php echo $page['html']; ?>
    </main>

    <script src="main.js"></script>
</body>
</html>
