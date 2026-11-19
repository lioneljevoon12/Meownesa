<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\Admin\DashboardController;
use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\KucingController;
use App\Core\Guard;

// ---- Publik (tanpa login) ----------------------------------------------------
$router->get('/', [new HomeController(), 'index']);
$router->get('/kucing', [new KucingController(), 'index']);
$router->get('/kucing/data', [new KucingController(), 'data']);   // Ajax: potongan HTML kartu
$router->get('/kucing/{id}', [new KucingController(), 'show']);

// ---- Autentikasi -------------------------------------------------------------
$router->get('/login', [new AuthController(), 'form']);
$router->post('/login', [new AuthController(), 'login']);
$router->post('/logout', [new AuthController(), 'logout'], [Guard::auth()]);

// ---- Admin (Admin Jaga Satwa + Super Admin) ------------------------------------
$router->get('/admin', [new DashboardController(), 'index'], [Guard::role('admin', 'superadmin')]);
