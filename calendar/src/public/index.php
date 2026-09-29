<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application;
use App\Auth;
use App\Env;

date_default_timezone_set(Env::get('APP_TIMEZONE', 'Europe/Madrid'));

Auth::init();

$router = require_once __DIR__ . '/../config/routes.php';
$app    = new Application($router);
$app->run();
