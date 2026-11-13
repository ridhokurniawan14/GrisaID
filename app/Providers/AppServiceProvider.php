<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::tokensCan([
            'students:read' => 'Membaca data siswa',
            'classes:read' => 'Membaca data kelas',
            'academic-years:read' => 'Membaca data tahun ajaran',
        ]);
    }
}
