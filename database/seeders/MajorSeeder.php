<?php

namespace Database\Seeders;

use App\Models\Major;
use Illuminate\Database\Seeder;

class MajorSeeder extends Seeder
{
    public function run(): void
    {
        $majors = [
            'AKL' => 'Akuntansi dan Keuangan Lembaga',
            'KUL' => 'Kuliner',
            'MPLB' => 'Manajemen Perkantoran dan Layanan Bisnis',
            'PH' => 'Perhotelan',
            'PM' => 'Pemasaran',
            'TJKT' => 'Teknik Jaringan Komputer dan Telekomunikasi',
        ];

        foreach ($majors as $code => $name) {
            Major::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
    }
}
