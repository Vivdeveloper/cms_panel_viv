<?php
/**
 * Public nav links for inner CMS pages — same URL shape as cms_page_url() (/page-slug).
 *
 * Requires config.php (loads cms_routing.php + slug helpers, cms_page_url, cms_home_url, cms_escape).
 *
 * Local dev (built-in server does not read .htaccess):
 *   php -S localhost:9000 router.php
 */

/** Href for one inner page in header / drawer menus. */
function cms_menu_inner_page_url(string $slug): string {
    $slug = trim($slug);
    if ($slug === '') {
        return cms_home_url();
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
    if (!function_exists('getAllCMSPages')) {
        require_once __DIR__ . '/cms_core.php';
    }
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
