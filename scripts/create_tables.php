<?php

declare(strict_types=1);

require __DIR__ . '/../src/helpers.php';

$config = require __DIR__ . '/../config/config.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$pdo = \App\Database\Connection::make($config['database']);
$schemaFile = __DIR__ . '/../database/schema.sql';

if (!file_exists($schemaFile)) {
    fwrite(STDERR, "Schema file not found: {$schemaFile}" . PHP_EOL);
    exit(1);
}

$schema = file_get_contents($schemaFile);

if ($schema === false) {
    fwrite(STDERR, "Unable to read schema file." . PHP_EOL);
    exit(1);
}

$pdo->exec($schema);

echo "Database tables created successfully." . PHP_EOL;

