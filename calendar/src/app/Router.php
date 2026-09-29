<?php

namespace App;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, array $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method'      => strtoupper($method),
            'path'        => $path,
            'pattern'     => $this->buildPattern($path),
            'controller'  => $handler[0],
            'action'      => $handler[1],
            'middlewares' => $middlewares,
        ];
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(): void
    {
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($route['middlewares'] as $middlewareClass) {
                $middleware = new $middlewareClass();
                if (!$middleware->handle()) {
                    return;
                }
            }

            $controller = new $route['controller']();
            $controller->{$route['action']}($params);
            return;
        }

        http_response_code(404);
        if ($this->isApiRequest($uri)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Not found']);
        } else {
            $errorView = __DIR__ . '/Views/errors/404.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo '<h1>404 Not Found</h1>';
            }
        }
    }

    /**
     * Requests answered in JSON, not HTML. `/api/` has no routes any more (the
     * per-user API was removed), but a stale client still gets a JSON 404.
     */
    public function isApiRequest(string $uri): bool
    {
        return str_starts_with($uri, '/api/') || str_starts_with($uri, '/mcp/');
    }
}
