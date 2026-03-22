<?php
// PHP Shared Header & Logic
$whatsapp = "9987842957";
$phone = "9987842957";
$repo = "Vivdeveloper/cms_panel_viv";
$brand = "creativ3.co";

function getHeader($title) {
    global $brand, $whatsapp;
    ?>
    <nav class="glass-nav">
        <a href="index.php" class="logo"><?php echo $brand; ?></a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="services.php">Services</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
            <a href="panel.php" style="color: #4facfe;">Admin</a>
        </div>
        <div class="contact-info">
            <a href="tel:<?php echo $whatsapp; ?>" class="contact-btn">Call: <?php echo $whatsapp; ?></a>
            <a href="https://wa.me/91<?php echo $whatsapp; ?>" class="contact-btn whatsapp">WhatsApp</a>
        </div>
    </nav>
    <?php
}

function getPanel() {
    global $repo;
    ?>
    <div id="control-panel">
        <button onclick="togglePanel(false)" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: #555; cursor: pointer; font-size: 24px;">&times;</button>
        <h2 style="margin-bottom: 20px; font-size: 24px; font-weight: 700; background: linear-gradient(45deg, #00f2fe, #4facfe); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Admin Control Panel</h2>
        <div style="margin-bottom: 15px;">
            <label style="display: block; color: #aaa; font-size: 14px;">PHP-Driven GitHub Sync (Flat-File)</label>
            <input type="text" id="repo-url" class="panel-input" value="<?php echo $repo; ?>">
        </div>
        <div style="margin-bottom: 15px;">
            <label style="display: block; color: #aaa; font-size: 14px;">Select Branch</label>
            <select id="branch-select" class="panel-select">
                <option value="main">main</option>
                <option value="master">master</option>
                <option value="dev">dev</option>
            </select>
        </div>
        <div style="margin-bottom: 20px; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
            <label style="display: block; color: #4facfe; font-size: 13px; font-weight: 700; margin-bottom: 12px;">FTP / Remote Deployment</label>
            <input type="text" id="ftp-host" class="panel-input" placeholder="ftp.yourdomain.com" style="margin-bottom: 10px; font-size: 13px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                <input type="text" id="ftp-user" class="panel-input" placeholder="FTP Username" style="font-size: 13px;">
                <input type="password" id="ftp-pass" class="panel-input" placeholder="FTP Password" style="font-size: 13px;">
            </div>
            <button class="panel-btn" onclick="deployToFTP()" style="background: linear-gradient(45deg, #f093fb, #f5576c); color: #fff;">
                🚀 Deploy to FTP
            </button>
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; color: #4facfe; font-size: 14px; font-weight: 700; margin-bottom: 10px;">Export & GitHub Sync</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button class="panel-btn" onclick="downloadProject('zip')" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1);">
                    Download ZIP
                </button>
                <button class="panel-btn" onclick="downloadProject('sync')" style="background: rgba(0,242,254,0.1); color: #00f2fe; border: 1px solid rgba(0,242,254,0.2);">
                    Push to Branch
                </button>
            </div>
        </div>

        <button class="panel-btn" onclick="saveSettings()">Save & Refresh Panel</button>
        <p style="font-size: 11px; color: #444; margin-top: 15px; text-align: center;">Continuous Deployment: Active</p>
    </div>
<?php
}
?>
