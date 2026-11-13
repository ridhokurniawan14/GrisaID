<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        // Sample Dapodik = tahun ajaran 2025/2026. Tanggal bisa disesuaikan nanti lewat panel admin.
        AcademicYear::updateOrCreate(
            ['code' => '2025/2026'],
            [
                'name' => 'Tahun Ajaran 2025/2026',
                'start_date' => '2025-07-14',
                'end_date' => '2026-06-27',
                'is_active' => true,
            ],
        );
    }
}
