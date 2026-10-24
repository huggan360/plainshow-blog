<?php
// Local development only: php -S localhost:8100 -t public app/dev-router.php
// Mirrors public/.htaccess: the API entry point and extensionless pages.
$public = dirname(__DIR__) . '/public';
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (preg_match('#^/api/v1(/|$)#', $path)) {
    $script = "$public/api/v1/index.php";
} elseif ($path !== '/' && is_file($public . $path) && !str_ends_with($path, '.php')) {
    return false;
} elseif (is_dir($public . $path) && is_file(rtrim($public . $path, '/') . '/index.php')) {
    $script = rtrim($public . $path, '/') . '/index.php';
} elseif (is_file($public . $path . '.php')) {
    $script = $public . $path . '.php';
} elseif (is_file($public . $path)) {
    $script = $public . $path;
} else {
    http_response_code(404);
    echo 'Not found';
    return true;
}
$_SERVER['SCRIPT_FILENAME'] = $script;
$_SERVER['PHP_SELF'] = substr($script, strlen($public));
chdir(dirname($script));
require $script;
