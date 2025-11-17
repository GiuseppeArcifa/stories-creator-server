<?php

declare(strict_types=1);

namespace App;

class Router
{
    /**
     * @var array<int, array{method:string, path:string, regex:string, handler:callable}>
     */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $regex = $this->convertPathToRegex($path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => $regex,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            if (preg_match($route['regex'], $uri, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_int($key)) {
                        $params[$key] = $value;
                    }
                }

                call_user_func($route['handler'], $params);

                return;
            }
        }

        jsonError('Route not found', 404);
    }

    /**
     * @return array<int, array{method:string, path:string}>
     */
    public function getRoutes(): array
    {
        return array_map(
            static fn (array $route): array => [
                'method' => $route['method'],
                'path' => $route['path'],
            ],
            $this->routes
        );
    }

    private function convertPathToRegex(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_-]*)\}#', '(?P<$1>[^/]+)', $path);
        $pattern = str_replace('/', '\/', $pattern ?? $path);

        return '#^' . $pattern . '$#';
    }
}

