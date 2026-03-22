<?php
/**
 * Shared admin sidebar (Navigation). Include after config.php (and session) is loaded.
 *
 * @param array $opts {
 *     @type string $mode       'spa' = admin.php tab switching; 'fullpage' = normal links (media/backup).
 *     @type string $main_tab   When mode=spa, active tab: pages|trash|settings|html_tags|contact|users|config
 *     @type string $active     When mode=fullpage, active item: pages|trash|media|backup|settings|html_tags|contact|users|config
 * }
 */
function cms_render_admin_sidebar_nav(array $opts = []) {
    $mode = ($opts['mode'] ?? 'fullpage') === 'spa' ? 'spa' : 'fullpage';
    $mainTab = (string) ($opts['main_tab'] ?? 'pages');
    $active = (string) ($opts['active'] ?? '');

    $items = [
        ['key' => 'pages', 'href' => 'admin.php', 'icon' => 'fa-file-alt', 'label' => 'Pages'],
        ['key' => 'trash', 'href' => 'admin.php?tab=trash', 'icon' => 'fa-trash-alt', 'label' => 'Trash'],
        ['key' => 'media', 'href' => 'media_manager.php', 'icon' => 'fa-camera-retro', 'label' => 'Media'],
        ['key' => 'backup', 'href' => 'backup.php', 'icon' => 'fa-cloud-download-alt', 'label' => 'Backup'],
        ['key' => 'settings', 'href' => 'admin.php?tab=settings', 'icon' => 'fa-cog', 'label' => 'Site settings'],
        ['key' => 'html_tags', 'href' => 'admin.php?tab=html_tags', 'icon' => 'fa-code', 'label' => 'HTML Tags'],
        ['key' => 'contact', 'href' => 'admin.php?tab=contact', 'icon' => 'fa-phone-alt', 'label' => 'Call now'],
        ['key' => 'users', 'href' => 'admin.php?tab=users', 'icon' => 'fa-users-cog', 'label' => 'User Roles'],
        ['key' => 'config', 'href' => 'admin.php?tab=config', 'icon' => 'fa-server', 'label' => 'Server Config'],
    ];

    echo '<div class="wp-admin-menu-backdrop" aria-hidden="true"></div>' . "\n";
    echo '<nav class="wp-admin-menu" id="wp-admin-menu" aria-label="Main menu">' . "\n";
    echo '<div class="menu-top">Navigation</div>' . "\n";

    foreach ($items as $item) {
        $key = $item['key'];
        $href = $item['href'];
        $icon = $item['icon'];
        $label = $item['label'];

        if ($mode === 'spa') {
            $isCurrent = ($mainTab === $key);
            $ext = ($key === 'media' || $key === 'backup');
            $classes = [];
            if (!$ext) {
                $classes[] = 'nav-btn';
            }
            if ($isCurrent) {
                $classes[] = 'current';
            }
            $classAttr = $classes !== [] ? ' class="' . cms_escape(implode(' ', $classes)) . '"' : '';
            echo '<a href="' . cms_escape($href) . '"' . $classAttr;
            if (!$ext) {
                echo ' onclick="switchMainTab(' . json_encode($key, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', event); return false;"';
            }
            echo '><i class="fas ' . cms_escape($icon) . '" aria-hidden="true"></i> ' . cms_escape($label) . '</a>' . "\n";
        } else {
            $isCurrent = ($active === $key);
            echo '<a href="' . cms_escape($href) . '"' . ($isCurrent ? ' class="current"' : '') . '><i class="fas ' . cms_escape($icon) . '" aria-hidden="true"></i> ' . cms_escape($label) . '</a>' . "\n";
        }
    }

    echo '<div class="menu-footer">' . "\n";
    echo '<a href="admin.php?logout=1"><i class="fas fa-power-off" aria-hidden="true"></i> Log Out</a>' . "\n";
    echo '</div>' . "\n";
    echo '</nav>' . "\n";
}
