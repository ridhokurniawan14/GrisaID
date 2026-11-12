<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // ID yang dipakai aplikasi lain (GrisaPay) untuk mengacu siswa

            // String, bukan angka: ada nol di depan, dan NIPD Dapodik berisi karakter "/".
            $table->string('nisn', 10)->nullable()->unique(); // siswa baru bisa belum punya NISN
            $table->string('nis', 30)->nullable()->unique();  // = NIPD Dapodik

            $table->string('full_name');
            $table->string('gender', 1);                      // L | P
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();          // No. WA siswa
            $table->unsignedSmallInteger('entry_year')->index(); // angkatan

            $table->string('status', 10)->default('aktif')->index(); // aktif | lulus | mutasi | do
            $table->timestamp('status_changed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 10);                   // ayah | ibu | wali
            $table->string('name')->nullable();
            $table->string('occupation', 80)->nullable();

            // Dapodik TIDAK punya nomor HP orang tua: diisi manual, jangan ditimpa saat re-import.
            $table->string('whatsapp', 20)->nullable();
            $table->boolean('is_primary_contact')->default(false);

            $table->timestamps();

            $table->unique(['student_id', 'relation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('students');
    }
};
