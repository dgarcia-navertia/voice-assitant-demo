<?php

namespace App\Controllers;

use App\Auth;

class HomeController extends Controller
{
    public function index(array $params = []): void
    {
        $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }
}
