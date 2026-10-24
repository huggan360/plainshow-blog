<?php
// One database connection for the blog (and LaTeX, which shares its tables).
// Credentials live in config/database.php, outside git and the web root.
if (!function_exists('wocDb')) {
    function wocDb(): mysqli
    {
        static $conn = null;
        if ($conn === null) {
            $config = require dirname(__DIR__) . '/config/database.php';
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn = new mysqli($config['host'], $config['user'], $config['password'], $config['database']);
            $conn->set_charset('utf8mb4');
        }

        return $conn;
    }
}
