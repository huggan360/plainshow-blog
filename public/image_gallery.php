<?php
// Album pictures: /image_gallery?file={name}[&w=480|960|1600]
require dirname(__DIR__) . '/vendor/autoload.php';

Blog\Domain\ImageServer::send(Blog\Domain\Albums::readDirectories(), (string) ($_GET['file'] ?? ''), (int) ($_GET['w'] ?? 0));
