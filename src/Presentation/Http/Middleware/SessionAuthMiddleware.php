<?php
declare(strict_types=1);

namespace App\Presentation\Http\Middleware;

use App\Shared\Http\Response;

final class SessionAuthMiddleware
{
    public function requireAuthenticated(): void
    {
        if ((int)($_SESSION['user_id'] ?? 0) <= 0) {
            Response::json(['success' => false, 'message' => 'No autenticado'], 401);
        }
    }
}
