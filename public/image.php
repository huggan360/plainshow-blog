<?php
// Post pictures: /image?file={name}[&w=480|960|1600]
require dirname(__DIR__) . '/vendor/autoload.php';

Blog\Domain\ImageServer::send(['/var/www/protected_pics', '/var/www/html/projects/hugohansson/woc/app/storage/posts'], (string) ($_GET['file'] ?? ''), (int) ($_GET['w'] ?? 0));
