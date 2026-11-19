<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;

final class AuthController
{
    public function form(): void
    {
        if (Auth::check()) {
            redirect(Auth::homeFor(Auth::user()['role']));
        }
        View::render('auth/login', ['title' => 'Login'], 'layouts/plain');
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['_old'] = ['email' => $email];
            flash('error', 'Email dan password wajib diisi dengan format yang benar.');
            redirect('login');
        }

        if (($sisa = Auth::lockedFor()) > 0) {
            flash('error', "Terlalu banyak percobaan. Coba lagi dalam {$sisa} detik.");
            redirect('login');
        }

        if (!Auth::attempt($email, $password)) {
            $_SESSION['_old'] = ['email' => $email];
            flash('error', 'Email atau password salah, atau akun tidak aktif.');
            redirect('login');
        }

        redirect(Auth::homeFor(Auth::user()['role']));
    }

    public function logout(): void
    {
        Auth::logout();
        flash('success', 'Anda telah keluar.');
        redirect('login');
    }
}
