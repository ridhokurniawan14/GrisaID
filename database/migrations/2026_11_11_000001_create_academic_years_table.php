<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 9)->unique(); // 2025/2026
            $table->string('name');              // Tahun Ajaran 2025/2026
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // Database menjamin hanya ada SATU tahun ajaran aktif.
        DB::statement('CREATE UNIQUE INDEX academic_years_single_active ON academic_years (is_active) WHERE is_active');
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};