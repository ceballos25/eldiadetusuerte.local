<?php
declare(strict_types=1);

namespace App\Shared\Routing;

use App\Shared\Http\Request;

final class Router
{
    /** @var array<string, callable> */
    private array $postRoutes = [];

    public function post(string $path, callable $handler): void
    {
        $this->postRoutes[$this->normalize($path)] = $handler;
    }

    public function dispatch(Request $request): mixed
    {
        $path = $this->normalize($request->path());

        if ($request->method() === 'POST' && isset($this->postRoutes[$path])) {
            return ($this->postRoutes[$path])($request);
        }

        return null;
    }

    private function normalize(string $path): string
    {
        return rtrim($path, '/') ?: '/';
    }
}
