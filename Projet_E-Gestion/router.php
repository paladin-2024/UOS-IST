<?php
// Dev-only router for `php -S`, mirroring .htaccess's rewrite rules (which
// only apply under real Apache -- the built-in server ignores .htaccess
// entirely and, with no router, falls back to index.php with an EMPTY
// $_GET['view'] for any pretty URL, silently showing the login page instead
// of the intended view). Not used in production (Apache handles this via
// .htaccess) and not referenced by deploy/ or CI.

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path = ltrim($uri, '/');

// Serve real files (assets, controller/*.php, api/*.php, etc.) as-is.
if ($path !== '' && is_file(__DIR__ . '/' . $path)) {
    return false;
}

// Everything else maps to index.php?view=<path>, same as the .htaccess rule.
// Leave $_GET['view'] unset for the bare root path, matching index.php's own
// "no view param" branch (which shows accueil.php) instead of forcing view=''
// (which would 403 -- '' isn't in allowed_views.php).
if ($path !== '') {
    $_GET['view'] = $path;
}
require __DIR__ . '/index.php';
