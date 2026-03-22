<?php
include 'config.php';

header('Content-Type: text/plain; charset=UTF-8');

$extra = trim((string) (getSiteSettings()['robots_extra'] ?? ''));

echo "User-agent: *\n";
echo "Allow: /\n\n";
echo 'Sitemap: ' . cms_url('sitemap.php') . "\n";
if ($extra !== '') {
    echo "\n" . $extra . "\n";
}
