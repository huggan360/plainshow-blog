<?php
// Sign-in moved to login.plainshow.se. Old links still work.
require_once dirname(__DIR__) . '/app/auth/plainshow.php';
use PlainShow\Kit\OidcClient;

$return = OidcClient::safeReturnPath($_GET['redirect'] ?? null, '/blogfeed');
if (wocCurrentUser() !== null) {
    header('Location: ' . $return, true, 302);
    exit;
}
header('Location: ' . wocAuth()->client()->authorizationUrl($_SESSION, $return), true, 302);
exit;
