<?php

namespace App\Middlewares;

use App\Auth;

class AdminOrManagerMiddleware implements Middleware
{
    public function handle(): bool
    {
        if (!Auth::isAdmin() && !Auth::isManager()) {
            http_response_code(403);
            require __DIR__ . '/../Views/errors/403.php';
            return false;
        }
        return true;
    }
}
