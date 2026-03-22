<?php
/**
 * Public site menu: inner-page URLs that work with Apache rewrites and with PHP’s built-in server.
 *
 * Requires config.php (cms_home_url, cms_page_url, cms_url, cms_escape) loaded first.
 */

/**
 * Built-in server without router.php does not apply .htaccess — clean /slug URLs 404.
 * In that case use view.php?page=slug so menu links still route correctly.
 */
function cms_menu_cli_needs_query_inner_urls(): bool {
    if (PHP_SAPI !== 'cli-server') {
        return false;
    }
    $base = basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));

    return strcasecmp($base, 'router.php') !== 0;
}

/** URL for a published inner page (used by header nav, drawers, etc.). */
function cms_menu_inner_page_url(string $slug): string {
    $slug = trim($slug);
    if ($slug === '') {
        return cms_home_url();
    }
    if (cms_menu_cli_needs_query_inner_urls()) {
        return cms_url('view.php?page=' . rawurlencode($slug));
    }

    return cms_page_url($slug);
}

/**
 * Whether a page may appear in the public header / drawer nav.
 * If allow_in_menu is absent (older JSON), default is true so existing sites keep links.
 */
function cms_page_show_in_public_menu(array $p): bool {
    if (!array_key_exists('allow_in_menu', $p)) {
        return true;
    }

    return filter_var($p['allow_in_menu'], FILTER_VALIDATE_BOOLEAN);
}

/**
 * Flat list of published inner-page links (desktop nav + mobile drawer), sorted by title.
 */
function cms_nav_page_links_html(): string {
    include_once __DIR__ . '/cms_core.php';
    $items = [];
    foreach (getAllCMSPages() as $p) {
        if ($p['is_home'] ?? false) {
            continue;
        }
        if (($p['status'] ?? 'draft') !== 'published') {
            continue;
        }
        if (!cms_page_show_in_public_menu($p)) {
            continue;
        }
        $items[] = $p;
    }
    usort($items, static function ($a, $b): int {
        $ta = trim((string) ($a['title'] ?? ''));
        $tb = trim((string) ($b['title'] ?? ''));
        if ($ta === '') {
            $ta = (string) ($a['slug'] ?? '');
        }
        if ($tb === '') {
            $tb = (string) ($b['slug'] ?? '');
        }

        return strcasecmp($ta, $tb);
    });
    $html = '';
    foreach ($items as $p) {
        $label = trim((string) ($p['title'] ?? ''));
        if ($label === '') {
            $label = ucwords(str_replace('-', ' ', (string) ($p['slug'] ?? '')));
        }
        $slug = (string) ($p['slug'] ?? '');
        $html .= '<a class="nav-page-link" href="' . cms_escape(cms_menu_inner_page_url($slug)) . '">' . cms_escape($label) . '</a>';
    }

    return $html;
}
