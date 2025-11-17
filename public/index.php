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
    $schemaManager->ensureAllTables();
} catch (Throwable $schemaException) {
    jsonError('Unable to prepare database: ' . $schemaException->getMessage(), 500);
    exit;
}

$storyRepository = new \App\Repositories\StoryRepository($pdo);
$textGenerationRepository = new \App\Repositories\TextGenerationRepository($pdo);
$audioGenerationRepository = new \App\Repositories\AudioGenerationRepository($pdo);

$storyController = new \App\Controllers\StoryController(
    $storyRepository,
    $textGenerationRepository,
    $audioGenerationRepository
);

$textGenerationController = new \App\Controllers\TextGenerationController(
    $textGenerationRepository,
    $storyRepository
);

$audioGenerationController = new \App\Controllers\AudioGenerationController(
    $audioGenerationRepository,
    $storyRepository,
    $textGenerationRepository
);

// Story routes
$router->add('GET', '/api/stories', [$storyController, 'index']);
$router->add('GET', '/api/stories/{id}', [$storyController, 'show']);
$router->add('POST', '/api/stories', [$storyController, 'store']);
$router->add('PUT', '/api/stories/{id}', [$storyController, 'update']);
$router->add('PATCH', '/api/stories/{id}', [$storyController, 'update']);
$router->add('DELETE', '/api/stories/{id}', [$storyController, 'destroy']);
$router->add('PATCH', '/api/stories/{id}/final-generations', [$storyController, 'updateFinalGenerations']);

// Text generation routes
$router->add('GET', '/api/stories/{id}/text-generations', [$textGenerationController, 'index']);
$router->add('POST', '/api/stories/{id}/text-generations', [$textGenerationController, 'store']);

// Audio generation routes
$router->add('GET', '/api/stories/{id}/audio-generations', [$audioGenerationController, 'index']);
$router->add('POST', '/api/stories/{id}/audio-generations', [$audioGenerationController, 'store']);

// API info route
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

