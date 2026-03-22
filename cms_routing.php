<?php
/**
 * Map HTTP requests to an inner CMS page slug (/slug, view.php?page=, or path under SCRIPT_NAME).
 * Keep path rules aligned with root .htaccess and router.php.
 */
require_once __DIR__ . '/slug.php';

/** Must match: RewriteRule ^([a-z0-9][a-z0-9\-]*)/?$ in root .htaccess */
if (!defined('CMS_INNER_PAGE_URL_REGEX')) {
    define('CMS_INNER_PAGE_URL_REGEX', '#^/([a-z0-9][a-z0-9\-]*)/?$#');
}

if (!function_exists('cms_request_inner_page_slug_from_path')) {
    /**
     * Slug from REQUEST_URI path only (ignores ?page=). Empty if URL is not …/page-slug.
     */
    function cms_request_inner_page_slug_from_path(): string {
        $raw = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $raw = is_string($raw) ? str_replace('\\', '/', $raw) : '';
        $path = trim($raw, '/');
        $base = trim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        if ($base !== '' && $base !== '.' && $base !== '/' && strpos($path, $base . '/') === 0) {
            $path = substr($path, strlen($base) + 1);
        }
        if (preg_match('#^view\.php/(.+)$#i', $path, $m)) {
            $path = trim($m[1], '/');
        }
        $path = trim($path, '/');
        if ($path === '' || strcasecmp($path, 'index.php') === 0 || strcasecmp($path, 'view.php') === 0) {
            return '';
        }
        if (preg_match('#^[a-z0-9][a-z0-9\-]*$#', $path)) {
            return cms_sanitize_slug($path);
        }

        return '';
    }
}

if (!function_exists('cms_request_inner_page_slug')) {
    /**
     * Prefer path (/whirlpool-service-center-mumbai) over ?page= so a stale or wrong query
     * cannot override the address bar slug (fixes wrong JSON when menu uses /slug but ?page= lingers).
     */
    function cms_request_inner_page_slug(): string {
        $fromPath = cms_request_inner_page_slug_from_path();
        if ($fromPath !== '') {
            return $fromPath;
        }
        if (!empty($_GET['page'])) {
            $s = cms_sanitize_slug((string) $_GET['page']);
            if ($s !== '') {
                return $s;
            }
        }

        return '';
    }
}
