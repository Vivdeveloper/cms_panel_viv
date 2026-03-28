<?php
/**
 * URL / filename slug normalization (pages_data/*.json basenames).
 * Loaded from config.php before cms_page_url() and menu.
 */
if (!function_exists('cms_sanitize_slug')) {
    function cms_sanitize_slug($str) {
        $str = str_replace(['_', ' '], '-', strtolower(trim((string) $str)));
        $str = preg_replace('/[^a-z0-9\-]/', '', $str);
        $str = preg_replace('/-+/', '-', $str);

        return trim($str, '-');
    }
}
