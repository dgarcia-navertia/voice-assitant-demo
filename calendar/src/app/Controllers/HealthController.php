<?php

namespace App\Controllers;

class HealthController extends Controller
{
    public function index(array $params = []): void
    {
        $this->json(['status' => 'ok']);
    }
}
