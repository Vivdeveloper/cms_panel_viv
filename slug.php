<?php
/**
 * URL / filename slug normalization (pages_data/*.json basenames).
 * Loaded from config.php before cms_page_url() and menu.
 */
if (!function_exists('cms_sanitize_slug')) {
    function cms_sanitize_slug($str) {
        $str = strtolower(trim(preg_replace('/\s+/', '-', (string) $str)));
        $str = preg_replace('/[^a-z0-9\-]/', '', $str);
        $str = preg_replace('/-+/', '-', $str);

        return trim($str, '-');
    }
}
