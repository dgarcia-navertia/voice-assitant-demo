<?php

namespace App;

class Application
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function run()
    {
        $bufferLevel = ob_get_level();
        try {
            $this->router->dispatch();
        } catch (\Throwable $e) {
            $this->serverError($e, $bufferLevel);
        }
    }

    /**
     * Logs the failure and answers with a generic 500. The message never reaches
     * the response: it can carry SQL errors, table names, file paths or raw user
     * input. The short reference ties what the user reports to the log line.
     */
    private function serverError(\Throwable $e, int $bufferLevel): void
    {
        $reference = bin2hex(random_bytes(4));
        error_log(sprintf(
            '[%s] %s %s — %s: %s at %s:%d',
            $reference,
            $_SERVER['REQUEST_METHOD'] ?? '-',
            $_SERVER['REQUEST_URI'] ?? '-',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        // A view that throws leaves Controller::render()'s buffer half full:
        // drop it so the error page is not appended to a broken page.
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if ($this->router->isApiRequest($uri)) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['error' => 'Internal Server Error', 'reference' => $reference]);
            return;
        }

        require __DIR__ . '/Views/errors/500.php';
    }
}
