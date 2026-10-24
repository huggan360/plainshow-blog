<?php
// Blog API entry point. Routes live in app/routes/api.php.
require dirname(__DIR__, 3) . '/app/bootstrap.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (preg_replace('#^/api/v1#', '', $path) === '/internal/notify') {
    Blog\Domain\InternalNotify::handle();
}

// Calls through plainshow.se/api come signed by core, naming the person.
if (PlainShow\Kit\Signed::present($_SERVER)) {
    $file = dirname(__DIR__, 3) . '/config/widgets.php';
    $multipart = str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'multipart/form-data');
    try {
        $verified = PlainShow\Kit\Signed::verify($_SERVER, $multipart ? '' : (string) file_get_contents('php://input'), is_file($file) ? (string) (require $file)['secret'] : '');
    } catch (PlainShow\Kit\KitException $error) {
        Blog\Api\Router::send(401, ['ok' => false, 'message' => $error->getMessage()]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_abort(); // nothing from a gateway call is kept in a session
    }
    $identity = gateway_identity(PlainShow\Kit\Signed::identity($verified));
    wocSyncUser($identity); // the blog profile exists even if they never opened the blog
    $_SESSION['user_id'] = $identity->id();
}

$router = new Blog\Api\Router();
require dirname(__DIR__, 3) . '/app/routes/api.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = preg_replace('#^/api/v1#', '', $path) ?: '/';
if ($path === '/openapi.json' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/plainshow.json'), true);
    echo json_encode(PlainShow\Kit\OpenApi::build($router->routes(), require dirname(__DIR__, 3) . '/app/routes/openapi-docs.php', [
        'title' => ps_t('PlainShow blog'), 'version' => (string) ($manifest['version'] ?? '1'), 'description' => ps_t('Posts, comments, stories, albums, wines and films.'),
    ], ps_url('core', '/api/blog/v1')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
