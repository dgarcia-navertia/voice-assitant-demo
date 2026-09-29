<?php

use App\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\AccountController;
use App\Controllers\DashboardController;
use App\Controllers\StoreController;
use App\Controllers\UserController;
use App\Controllers\CommercialController;
use App\Controllers\AppointmentController;
use App\Controllers\CalendarController;
use App\Controllers\BlockingEventController;
use App\Controllers\HolidayController;
use App\Controllers\ScheduleOverrideController;
use App\Controllers\ClientController;
use App\Controllers\LeadController;
use App\Controllers\CallController;
use App\Controllers\DialOutController;
use App\Controllers\HealthController;
use App\Controllers\SettingsController;
use App\Controllers\Api\InternalApiController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\AdminOrManagerMiddleware;
use App\Middlewares\InternalApiMiddleware;

$router = new Router();

$auth  = [AuthMiddleware::class];
$admin = [AuthMiddleware::class, AdminMiddleware::class];
$adminOrManager = [AuthMiddleware::class, AdminOrManagerMiddleware::class];

// Sondeo de salud (Docker / Caddy). Publico y sin base de datos.
$router->add('GET', '/health', [HealthController::class, 'index']);

// Root + auth
$router->add('GET',  '/', [HomeController::class, 'index']);
$router->add('GET',  '/login',  [AuthController::class, 'showLogin']);
$router->add('POST', '/login',  [AuthController::class, 'login']);
$router->add('POST', '/logout', [AuthController::class, 'logout'], $auth);

// Mi cuenta
$router->add('GET',  '/account',          [AccountController::class, 'index'],          $auth);
$router->add('POST', '/account/password', [AccountController::class, 'updatePassword'], $auth);

// Agenda
$router->add('GET', '/dashboard', [DashboardController::class, 'index'], $auth);
$router->add('GET', '/calendar',  [CalendarController::class, 'index'],  $auth);

// Tiendas (admin)
$router->add('GET',    '/stores',           [StoreController::class, 'index'],   $admin);
$router->add('GET',    '/stores/create',    [StoreController::class, 'create'],  $admin);
$router->add('POST',   '/stores',           [StoreController::class, 'store'],   $admin);
$router->add('GET',    '/stores/{id}/edit', [StoreController::class, 'edit'],    $admin);
$router->add('PUT',    '/stores/{id}',      [StoreController::class, 'update'],  $admin);
$router->add('DELETE', '/stores/{id}',      [StoreController::class, 'destroy'], $admin);

// Usuarios (admin)
$router->add('GET',  '/users',           [UserController::class, 'index'],  $admin);
$router->add('GET',  '/users/create',    [UserController::class, 'create'], $admin);
$router->add('POST', '/users',           [UserController::class, 'store'],  $admin);
$router->add('GET',  '/users/{id}/edit', [UserController::class, 'edit'],   $admin);
$router->add('PUT',  '/users/{id}',      [UserController::class, 'update'], $admin);

// Comerciales (admin, o manager acotado a su tienda)
$router->add('GET',    '/commercials',           [CommercialController::class, 'index'],   $adminOrManager);
$router->add('GET',    '/commercials/create',    [CommercialController::class, 'create'],  $adminOrManager);
$router->add('POST',   '/commercials',           [CommercialController::class, 'store'],   $adminOrManager);
$router->add('GET',    '/commercials/{id}/edit', [CommercialController::class, 'edit'],    $adminOrManager);
$router->add('PUT',    '/commercials/{id}',      [CommercialController::class, 'update'],  $adminOrManager);
$router->add('DELETE', '/commercials/{id}',      [CommercialController::class, 'destroy'], $adminOrManager);

$router->add('GET',    '/commercials/{id}/overrides',              [ScheduleOverrideController::class, 'index'],   $adminOrManager);
$router->add('POST',   '/commercials/{id}/overrides',              [ScheduleOverrideController::class, 'store'],   $adminOrManager);
$router->add('DELETE', '/commercials/{id}/overrides/{overrideId}', [ScheduleOverrideController::class, 'destroy'], $adminOrManager);

// Citas
$router->add('GET',  '/appointments',        [AppointmentController::class, 'index'],  $auth);
$router->add('GET',  '/appointments/create', [AppointmentController::class, 'create'], $auth);
$router->add('POST', '/appointments',        [AppointmentController::class, 'store'],  $auth);
// Debe ir antes de /appointments/{id}: gana la primera ruta que casa.
$router->add('GET',  '/appointments/slots',  [AppointmentController::class, 'slots'],  $auth);
$router->add('GET',  '/appointments/{id}',   [AppointmentController::class, 'show'],   $auth);
$router->add('POST', '/appointments/{id}/confirm', [AppointmentController::class, 'confirm'], $auth);
$router->add('POST', '/appointments/{id}/cancel',  [AppointmentController::class, 'cancel'],  $auth);

// Bloqueos de horario
$router->add('GET',    '/blocking-events/create', [BlockingEventController::class, 'create'],  $auth);
$router->add('POST',   '/blocking-events',        [BlockingEventController::class, 'store'],   $auth);
$router->add('DELETE', '/blocking-events/{id}',   [BlockingEventController::class, 'destroy'], $auth);

// Festivos (admin)
$router->add('GET',    '/holidays',      [HolidayController::class, 'index'],   $admin);
$router->add('POST',   '/holidays',      [HolidayController::class, 'store'],   $admin);
$router->add('DELETE', '/holidays/{id}', [HolidayController::class, 'destroy'], $admin);

// Ajustes globales (admin): numero de traspaso
$router->add('GET',  '/settings',         [SettingsController::class, 'index'],         $admin);
$router->add('POST', '/settings/handoff', [SettingsController::class, 'updateHandoff'], $admin);

// Clientes, leads
$router->add('GET',  '/clients', [ClientController::class, 'index'], $auth);
$router->add('POST', '/clients', [ClientController::class, 'store'], $auth);
$router->add('GET',  '/leads',   [LeadController::class, 'index'],   $auth);

// Llamadas + Dial Out
$router->add('GET',  '/calls',                    [CallController::class, 'index'],    $auth);
$router->add('GET',  '/calls/{sid}',              [CallController::class, 'show'],     $auth);
$router->add('GET',  '/dial-out',                 [DialOutController::class, 'index'],  $auth);
$router->add('POST', '/dial-out',                 [DialOutController::class, 'dial'],   $auth);
$router->add('GET',  '/dial-out/status/{sid}',    [DialOutController::class, 'status'], $auth);

// API interna del bot (token compartido)
$api = [InternalApiMiddleware::class];
$router->add('GET',  '/mcp/stores',           [InternalApiController::class, 'stores'],       $api);
$router->add('GET',  '/mcp/stores/{id}',      [InternalApiController::class, 'storeById'],    $api);
$router->add('GET',  '/mcp/availability',     [InternalApiController::class, 'availability'], $api);
$router->add('GET',  '/mcp/clients/by-phone', [InternalApiController::class, 'clientByPhone'], $api);
$router->add('POST', '/mcp/clients',          [InternalApiController::class, 'createClient'], $api);
$router->add('POST', '/mcp/appointments',     [InternalApiController::class, 'createAppointment'], $api);
$router->add('POST', '/mcp/transcripts',      [InternalApiController::class, 'createTranscript'], $api);
$router->add('POST', '/mcp/transcripts/batch', [InternalApiController::class, 'createTranscriptBatch'], $api);
$router->add('POST', '/mcp/leads',            [InternalApiController::class, 'createLead'],   $api);
$router->add('GET',  '/mcp/settings/handoff', [InternalApiController::class, 'handoffSetting'], $api);
$router->add('POST', '/mcp/calls/status',     [InternalApiController::class, 'callStatus'],   $api);

return $router;
