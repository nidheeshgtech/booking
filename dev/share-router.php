<?php
/**
 * Router for `php -S` when sharing the prototype through a public tunnel.
 * Serves only the demo pages and the API — never the rest of the folder
 * (client spreadsheets, screenshots, data files).
 *
 *   php -S 127.0.0.1:8099 -t /Applications/MAMP/htdocs/booking dev/share-router.php
 */
$routes = [
    '/'                => '/index.html',
    '/index.html'      => '/index.html',
    '/onepage.html'    => '/onepage.html',
    '/api/nearest.php' => '/api/nearest.php',
];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!isset($routes[$path])) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

$file = dirname(__DIR__) . $routes[$path];
if (str_ends_with($file, '.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($file);
} else {
    require $file;
}
return true;
