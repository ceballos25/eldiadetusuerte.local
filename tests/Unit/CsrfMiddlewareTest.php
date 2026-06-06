<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Presentation\Http\Middleware\CsrfMiddleware;
use App\Shared\Http\Request;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['csrf_token' => 'test-csrf-token'];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function request(array $body = [], array $server = []): Request
    {
        return new Request([], $body, $server, [], []);
    }

    public function testIsValidWithBodyToken(): void
    {
        $middleware = new CsrfMiddleware();
        $request = $this->request([
            'action' => 'actualizar',
            'csrf_token' => 'test-csrf-token',
        ]);

        self::assertTrue($middleware->isValid($request));
    }

    public function testIsValidWithHeaderToken(): void
    {
        $middleware = new CsrfMiddleware();
        $request = $this->request(
            ['action' => 'actualizar'],
            ['HTTP_X_CSRF_TOKEN' => 'test-csrf-token']
        );

        self::assertTrue($middleware->isValid($request));
    }

    public function testIsInvalidWithWrongToken(): void
    {
        $middleware = new CsrfMiddleware();
        $request = $this->request([
            'action' => 'actualizar',
            'csrf_token' => 'wrong',
        ]);

        self::assertFalse($middleware->isValid($request));
    }

    public function testIsInvalidWithEmptyToken(): void
    {
        $middleware = new CsrfMiddleware();
        $request = $this->request(['action' => 'actualizar']);

        self::assertFalse($middleware->isValid($request));
    }
}
