<?php

namespace Tests\Unit;

use App\Application;
use App\Router;
use PHPUnit\Framework\TestCase;

class ApplicationErrorTest extends TestCase
{
    private string $log;

    protected function setUp(): void
    {
        $this->log = (string) tempnam(sys_get_temp_dir(), 'app-error-');
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }

    public function test_a_database_error_shows_a_generic_page_and_goes_to_the_log(): void
    {
        $output = $this->failingRequest('/appointments/7', new \PDOException("SQLSTATE[42S22]: Unknown column 'secret_col'"));

        $this->assertStringContainsString('Algo ha fallado', $output);
        $this->assertStringNotContainsString('SQLSTATE', $output);
        $this->assertStringNotContainsString('secret_col', $output);
        $this->assertStringContainsString("Unknown column 'secret_col'", $this->logged());
    }

    public function test_php_errors_are_caught_not_only_exceptions(): void
    {
        // p. ej. POST /login con email[]=x: trim() recibe un array.
        $output = $this->failingRequest('/login', new \TypeError('trim(): Argument #1 ($string) must be of type string, array given'));

        $this->assertStringContainsString('Algo ha fallado', $output);
        $this->assertStringNotContainsString('trim()', $output);
        $this->assertStringContainsString('TypeError', $this->logged());
    }

    public function test_the_reference_on_the_page_matches_the_log_line(): void
    {
        $output = $this->failingRequest('/dashboard', new \RuntimeException('boom'));

        $this->assertSame(1, preg_match('#<code[^>]*>([0-9a-f]{8})</code>#', $output, $match));
        $this->assertStringContainsString('[' . $match[1] . '] GET /dashboard', $this->logged());
    }

    public function test_a_half_rendered_view_is_discarded(): void
    {
        $output = $this->failingRequest('/appointments', new \RuntimeException('boom'), '<table>fila a medias');

        $this->assertStringNotContainsString('fila a medias', $output);
        $this->assertStringContainsString('Algo ha fallado', $output);
    }

    public function test_machine_endpoints_get_json_without_the_message(): void
    {
        $output = $this->failingRequest('/mcp/appointments', new \PDOException('SQLSTATE[HY000] [2002] Connection refused'));
        $body   = json_decode($output, true);

        $this->assertSame('Internal Server Error', $body['error']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $body['reference']);
        $this->assertStringNotContainsString('SQLSTATE', $output);
    }

    /** Runs the app with a router that fails mid-request, as a controller would. */
    private function failingRequest(string $uri, \Throwable $error, string $partialOutput = ''): string
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;

        $router = new class($error, $partialOutput) extends Router {
            public function __construct(private \Throwable $error, private string $partialOutput)
            {
            }

            public function dispatch(): void
            {
                ob_start(); // como Controller::render() antes de incluir la vista
                echo $this->partialOutput;
                throw $this->error;
            }
        };

        // PHPUnit redirige error_log al empezar cada test (después de setUp), así
        // que se desvía aquí, solo durante la petición, y se le devuelve después.
        $phpunitLog = ini_set('error_log', $this->log);
        try {
            ob_start();
            (new Application($router))->run();
            return (string) ob_get_clean();
        } finally {
            ini_set('error_log', (string) $phpunitLog);
        }
    }

    private function logged(): string
    {
        return (string) file_get_contents($this->log);
    }
}
