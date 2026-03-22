<?php
include 'config.php';
include 'cms_core.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = rtrim(cms_site_url(), '/');
$urls = [];

$urls[] = [
    'loc'     => cms_home_url(),
    'lastmod' => null,
];

foreach (getAllCMSPages() as $p) {
    if (($p['status'] ?? 'draft') !== 'published') {
        continue;
    }
    if ($p['is_home'] ?? false) {
        continue;
    }
    $slug = $p['slug'] ?? '';
    if ($slug === '') {
        continue;
    }
    $lm = $p['updated'] ?? null;
    $urls[] = [
        'loc'     => cms_page_url($slug),
        'lastmod' => $lm ? date('Y-m-d', strtotime((string) $lm)) : null,
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
    if (!empty($u['lastmod'])) {
        echo '<lastmod>' . htmlspecialchars($u['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</lastmod>';
    }
    echo "</url>\n";
}
echo '</urlset>';
