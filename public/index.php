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

handleCors();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($path, PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

$router = new \App\Router();

$pdo = \App\Database\Connection::make($config['database']);

try {
    $schemaManager = new \App\Database\SchemaManager($pdo, __DIR__ . '/../database/schema.sql');
    $schemaManager->ensureStoriesTable();
} catch (Throwable $schemaException) {
    jsonError('Unable to prepare database: ' . $schemaException->getMessage(), 500);
    exit;
}

$storyRepository = new \App\Repositories\StoryRepository($pdo);
$storyController = new \App\Controllers\StoryController($storyRepository);

$router->add('GET', '/api/stories', [$storyController, 'index']);
$router->add('GET', '/api/stories/{id}', [$storyController, 'show']);
$router->add('POST', '/api/stories', [$storyController, 'store']);
$router->add('PUT', '/api/stories/{id}', [$storyController, 'update']);
$router->add('PATCH', '/api/stories/{id}', [$storyController, 'update']);
$router->add('DELETE', '/api/stories/{id}', [$storyController, 'destroy']);
$router->add('GET', '/api', static function () use ($router): void {
    jsonResponse([
        'routes' => $router->getRoutes(),
    ]);
});

try {
    $router->dispatch($method, $path);
} catch (Throwable $exception) {
    jsonError('Unexpected server error: ' . $exception->getMessage(), 500);
}

