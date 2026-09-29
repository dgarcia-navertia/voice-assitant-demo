<?php

namespace App\Middlewares;

interface Middleware
{
    /**
     * @return bool True to continue, False to stop execution
     */
    public function handle(): bool;
}
