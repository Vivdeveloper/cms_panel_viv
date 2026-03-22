<?php
// PHP Shared Header & Logic
$whatsapp = "9987842957";
$phone = "9987842957";
$repo = "Vivdeveloper/cms_panel_viv";
$brand = "creativ3.co";

function getHeader($title) {
    global $brand, $whatsapp, $phone;
    ?>
    <nav class="glass-nav">
        <a href="index.php" class="logo"><?php echo $brand; ?></a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <?php 
            include_once 'cms_core.php';
            $allPages = getAllCMSPages();
            foreach ($allPages as $p): 
                if ($p['is_home'] ?? false) continue;
            ?>
                <a href="view.php?page=<?php echo $p['slug']; ?>"><?php echo ucwords(str_replace('-', ' ', $p['slug'])); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="contact-btn">
            <span class="call-btn">Call: <?php echo $phone; ?></span>
            <a href="https://wa.me/<?php echo $whatsapp; ?>" class="whatsapp-btn">WhatsApp</a>
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
        <div style="margin-bottom: 20px;">
            <label style="display: block; color: #aaa; font-size: 14px;">Select Dynamic Branch</label>
            <select id="branch-select" class="panel-select">
                <option value="main">main</option>
                <option value="master">master</option>
                <option value="dev">dev</option>
                <option value="staging">staging</option>
            </select>
            <div id="branch-status" style="font-size: 11px; color: #4facfe; margin-top: 5px;">GitHub Dynamic Sync Integrated</div>
        </div>
        <button class="panel-btn" onclick="saveSettings()">Apply Dynamic Update</button>
        <div style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px; text-align: center;">
            <a href="admin.php" style="color: #00f2fe; font-size: 14px; text-decoration: none; font-weight: 700;">✨ Professional Admin Dashboard</a>
        </div>
        <div style="margin-top: 5px; text-align: center;">
            <a href="panel.php" style="color: #4facfe; font-size: 13px; text-decoration: none; font-weight: 500;">Open Private Full-Screen Dashboard →</a>
        </div>
        <p style="font-size: 11px; color: #444; margin-top: 15px; text-align: center;">Continuous Deployment: Active</p>
    </div>
<?php
}
?>
