<?php
/**
 * Sidebar nav item definitions (keys match cms_admin_menu_keys() in cms_core.php).
 */
function cms_admin_nav_items() {
    static $defs = [
        'pages'     => ['href' => 'admin.php', 'icon' => 'fa-file-alt', 'label' => 'Pages'],
        'trash'     => ['href' => 'admin.php?tab=trash', 'icon' => 'fa-trash-alt', 'label' => 'Trash'],
        'media'     => ['href' => 'media_manager.php', 'icon' => 'fa-camera-retro', 'label' => 'Media'],
        'backup'    => ['href' => 'backup.php', 'icon' => 'fa-cloud-download-alt', 'label' => 'Backup'],
        'settings'  => ['href' => 'admin.php?tab=settings', 'icon' => 'fa-cog', 'label' => 'Site settings'],
        'html_tags' => ['href' => 'admin.php?tab=html_tags', 'icon' => 'fa-code', 'label' => 'HTML Tags'],
        'contact'       => ['href' => 'admin.php?tab=contact', 'icon' => 'fa-phone-alt', 'label' => 'Call now'],
        'contact_form'  => ['href' => 'admin.php?tab=contact_form', 'icon' => 'fa-envelope-open-text', 'label' => 'Contact form'],
        'crm'           => ['href' => 'admin.php?tab=crm', 'icon' => 'fa-clipboard-list', 'label' => 'CRM'],
        'users'         => ['href' => 'admin.php?tab=users', 'icon' => 'fa-users-cog', 'label' => 'User Roles'],
        'config'    => ['href' => 'admin.php?tab=config', 'icon' => 'fa-server', 'label' => 'Server Config'],
    ];
    $items = [];
    foreach (cms_admin_menu_keys() as $key) {
        if (!isset($defs[$key])) {
            continue;
        }
        $items[] = ['key' => $key] + $defs[$key];
    }
    return $items;
}

/**
 * Shared admin sidebar (Navigation). Include after config.php (and session) is loaded.
 *
 * @param array $opts {
 *     @type string $mode          'spa' | 'fullpage'
 *     @type string $main_tab      When mode=spa, active tab id
 *     @type string $active        When mode=fullpage, active item key
 *     @type array|null $allowed_keys If set, only these item keys are shown (e.g. from cms_user_allowed_menu_keys).
 * }
 */
function cms_render_admin_sidebar_nav(array $opts = []) {
    $mode = ($opts['mode'] ?? 'fullpage') === 'spa' ? 'spa' : 'fullpage';
    $mainTab = (string) ($opts['main_tab'] ?? 'pages');
    $active = (string) ($opts['active'] ?? '');
    $allowed = $opts['allowed_keys'] ?? null;
    $items = cms_admin_nav_items();
    if (is_array($allowed) && $allowed !== []) {
        $allowed = array_flip($allowed);
        $items = array_values(array_filter($items, static function ($item) use ($allowed) {
            return isset($allowed[$item['key']]);
        }));
    }

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
