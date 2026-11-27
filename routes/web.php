<?php

use App\Http\Controllers\SsoAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [SsoAuthController::class, 'showLogin'])->name('login');   // nama "login" dipakai Passport untuk mengarahkan tamu
    Route::post('/masuk', [SsoAuthController::class, 'login'])->name('sso.login');
});
Route::middleware('auth')->group(function () {
    Route::get('/akun/ganti-password', [SsoAuthController::class, 'showChangePassword'])->name('sso.password');
    Route::post('/akun/ganti-password', [SsoAuthController::class, 'changePassword'])->name('sso.password.update');
    Route::post('/keluar', [SsoAuthController::class, 'logout'])->name('sso.logout');
    Route::get('/akun', [SsoAuthController::class, 'home'])->name('sso.home');
});
