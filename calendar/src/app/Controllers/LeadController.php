<?php

namespace App\Controllers;

use App\Models\Lead;

class LeadController extends Controller
{
    public function index(array $params = []): void
    {
        $this->render('leads/index.php', ['leads' => Lead::listWithStore()]);
    }
}
