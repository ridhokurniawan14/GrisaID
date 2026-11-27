<?php

namespace App\Providers;

use App\Models\Passport\Client;
use Carbon\CarbonInterval;
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
        Passport::authorizationView('sso.authorize');
        Passport::useClientModel(Client::class);

        // SATU kali saja: pemanggilan tokensCan() berikutnya MENIMPA yang sebelumnya.
        Passport::tokensCan([
            'students:read' => 'Membaca data siswa',
            'classes:read' => 'Membaca data kelas',
            'academic-years:read' => 'Membaca data tahun ajaran',
            'me:read' => 'Membaca identitas pengguna',
            'bills:read' => 'Membaca status tagihan',
        ]);

        Passport::tokensExpireIn(CarbonInterval::hours(2));
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
    }
}
