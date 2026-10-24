<?php
// Maintenance commands: php app/console.php publish-ui | migrate
declare(strict_types=1);

$command = $argv[1] ?? '';
if ($command === 'publish-ui') {
    $source = dirname(__DIR__) . '/vendor/plainshow/kit/ui';
    $target = dirname(__DIR__) . '/public/ui';
    if (!is_dir($source)) {
        fwrite(STDERR, "The PlainShow kit is not installed.\n");
        exit(1);
    }
    $copy = function (string $from, string $to) use (&$copy): void {
        if (!is_dir($to)) {
            mkdir($to, 0775, true);
        }
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            is_dir("$from/$entry") ? $copy("$from/$entry", "$to/$entry") : copy("$from/$entry", "$to/$entry");
        }
    };
    foreach (['css', 'fonts', 'js', 'brand'] as $directory) {
        $copy("$source/$directory", "$target/$directory");
    }
    echo "PlainShow UI published.\n";
    exit(0);
}
if ($command === 'migrate') {
    // database/migrations/*.sql, each once and in name order. The first one is
    // the schema as it was in 2026 and changes nothing where the tables exist.
    require __DIR__ . '/db.php';
    $db = wocDb();
    $db->query('CREATE TABLE IF NOT EXISTS `blog_migrations` (`name` varchar(190) NOT NULL PRIMARY KEY, `ran_at` datetime NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $done = array_column($db->query('SELECT `name` FROM `blog_migrations`')->fetch_all(MYSQLI_ASSOC), 'name');
    $files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
    sort($files);
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $done, true)) {
            continue;
        }
        $db->multi_query((string) file_get_contents($file));
        do {
            if ($result = $db->store_result()) {
                $result->free();
            }
        } while ($db->more_results() && $db->next_result());
        $stmt = $db->prepare('INSERT INTO `blog_migrations` (`name`, `ran_at`) VALUES (?, NOW())');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        echo "Migrated $name\n";
    }
    exit(0);
}
fwrite(STDERR, "Usage: php app/console.php publish-ui|migrate\n");
exit(1);
