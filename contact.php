<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - <?php echo $brand; ?></title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
</head>
<body>
    <div id="canvas-container"></div>
    <?php getPanel(); ?>
    <?php getHeader('Contact'); ?>

    <main class="section">
        <h1>Connect with <span class="gradient-text">us</span></h1>
        <p class="subtitle">Ready to transform your digital presence? Reach out via our PHP-powered contact system.</p>
        <div class="grid">
            <div class="card" style="text-align: center;">
                <h3>WhatsApp Direct</h3>
                <p>Chat with a dedicated specialist instantly.</p>
                <a href="https://wa.me/91<?php echo $whatsapp; ?>" class="contact-btn whatsapp" style="margin-top: 20px; display: inline-flex;">WhatsApp Now</a>
            </div>
            <div class="card" style="text-align: center;">
                <h3>Direct Call</h3>
                <p>Available 24/7 for technical and design consultation.</p>
                <a href="tel:<?php echo $phone; ?>" class="contact-btn" style="margin-top: 20px; display: inline-flex;"><?php echo $phone; ?></a>
            </div>
            <div class="card" style="text-align: center;">
                <h3>PHP Support</h3>
                <p>For custom deployment and GitHub support.</p>
                <p style="color: #666; margin-top: 15px; font-size: 14px;"><?php echo $repo; ?></p>
            </div>
        </div>
    </main>
    <script src="main.js"></script>
</body>
</html>
