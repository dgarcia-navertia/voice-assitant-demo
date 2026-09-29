<?php

namespace App\Controllers;

use App\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin(array $params = []): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->renderRaw('auth/login.php');
    }

    public function login(array $params = []): void
    {
        $email    = trim($this->input('email', ''));
        $password = $this->input('password', '');
        $user     = User::findByEmail($email);

        if (!$user || !User::verifyPassword($password, $user['password'])) {
            $this->renderRaw('auth/login.php', ['error' => 'Email o contraseña incorrectos.']);
            return;
        }

        Auth::login($user);
        $this->redirect('/dashboard');
    }

    public function logout(array $params = []): void
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
