<?php

namespace App\Controllers;

use App\Auth;

abstract class Controller
{
    protected function render(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = __DIR__ . '/../Views/' . $view;

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        $user = Auth::user();
        require __DIR__ . '/../Views/layouts/main.php';
    }

    protected function renderRaw(string $view, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../Views/' . $view;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function rawRequestBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    protected function requestBody(): array
    {
        return json_decode($this->rawRequestBody(), true) ?? [];
    }

    protected function validateFields(array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (empty($this->input($field))) {
                $errors[$field] = "El campo {$field} es obligatorio.";
            }
        }
        return $errors;
    }

    protected function notFound(): void
    {
        http_response_code(404);
        $errorView = __DIR__ . '/../Views/errors/404.php';
        if (file_exists($errorView)) {
            require $errorView;
        } else {
            echo '<h1>404 Not Found</h1>';
        }
    }

    protected function forbidden(): void
    {
        http_response_code(403);
        $errorView = __DIR__ . '/../Views/errors/403.php';
        if (file_exists($errorView)) {
            require $errorView;
        } else {
            echo '<h1>403 Forbidden</h1>';
        }
    }

    /** Token CSRF de la sesion (para acciones con coste, como llamar). */
    protected function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    protected function csrfValid(): bool
    {
        $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
        return $sent !== '' && hash_equals($this->csrfToken(), $sent);
    }
}
