<?php

namespace App\Middlewares;

use App\Auth;

class AuthMiddleware implements Middleware
{
    public function handle(): bool
    {
        if (!Auth::check()) {
            header('Location: /login');
            return false;
        }
        return true;
    }
}
