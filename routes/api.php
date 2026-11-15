<?php

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\ClassroomController;
use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner as ClientScope;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/ping', fn(Request $request) => response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]))->middleware(ClientScope::class)->name('ping');

    Route::middleware(ClientScope::using('students:read'))->group(function () {
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        // {identifier} = UUID atau NISN (NIS memuat karakter "/", gunakan filter ?nis= pada daftar siswa)
        Route::get('/students/{identifier}', [StudentController::class, 'show'])
            ->where('identifier', '[0-9a-fA-F-]{10,36}')
            ->name('students.show');
    });

    Route::middleware(ClientScope::using('classes:read'))->group(function () {
        Route::get('/classes', [ClassroomController::class, 'index'])->name('classes.index');
    });

    Route::middleware(ClientScope::using('academic-years:read'))->group(function () {
        Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::get('/academic-years/current', [AcademicYearController::class, 'current'])->name('academic-years.current');
    });
});
