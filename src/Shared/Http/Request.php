<?php
declare(strict_types=1);

namespace App\Shared\Http;

final class Request
{
    public function __construct(
        private readonly array $query,
        private readonly array $request,
        private readonly array $server,
        private readonly array $cookies,
        private readonly array $files
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);
    }

    public function method(): string
    {
        return strtoupper((string)($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = (string)($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? preg_replace('#/+#', '/', $path) : '/';

        return $path ? rtrim($path, '/') ?: '/' : '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->request;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$headerKey] ?? $default;
    }
}
