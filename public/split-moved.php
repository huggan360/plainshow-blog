<?php
// Split moved out of the blog into its own app; old /split links lead there.
require_once dirname(__DIR__) . '/app/bootstrap.php';

$split = ps_url('split');
if ($split === '') {
    http_response_code(404);
    exit;
}
$group = (int) ($_GET['group_id'] ?? 0);
header('Location: ' . $split . ($group > 0 ? '/group?id=' . $group : '/'), true, 301);
