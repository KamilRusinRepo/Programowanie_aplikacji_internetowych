<?php

declare(strict_types=1);

namespace FlashMind\Http;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = compact('method', 'path', 'handler');
    }

    public function dispatch(Request $request, ?callable $notFoundHandler = null): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            $parameters = $this->match($route['path'], $request->path);

            if ($parameters === null) {
                continue;
            }

            $handler = $route['handler'];

            if (is_array($handler)) {
                $target = $handler[0];
                $method = $handler[1];
                $target->{$method}($request, ...array_values($parameters));
                return;
            }

            $handler($request, ...array_values($parameters));
            return;
        }

        http_response_code(404);

        if ($notFoundHandler !== null) {
            $notFoundHandler();
            return;
        }

        echo '404 Not Found';
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        if ($routePath === $requestPath) {
            return [];
        }

        $routePattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);
        $routePattern = '#^' . $routePattern . '$#';

        if (!preg_match($routePattern, $requestPath, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}