<?php

use App\Models\Classroom;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data guru (sumber: "Daftar Guru" Dapodik). NIK wajib & unik (dicocokkan lewat hash); NUPTK boleh kosong.
        Schema::create('teachers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('front_title', 50)->nullable();   // gelar depan: Drs., Hj.
            $table->string('back_title', 100)->nullable();   // gelar belakang: S.Pd., M.Pd.
            $table->string('gender', 1)->nullable();         // L | P
            $table->text('nik')->nullable();                 // terenkripsi
            $table->string('nik_hash', 64)->nullable()->unique();
            $table->string('nuptk', 20)->nullable()->unique();
            $table->string('nip', 25)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('employment_status', 50)->nullable(); // GTY/PTY, Guru Honor Sekolah
            $table->string('additional_duty')->nullable();       // tugas tambahan (Dapodik)
            $table->string('status', 10)->default('aktif')->index(); // aktif | nonaktif
            $table->timestamps();
        });

        // Wali kelas: satu kelas (yang sudah per tahun ajaran) punya satu wali; satu guru hanya satu kelas per tahun (dijaga service).
        Schema::create('homeroom_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Classroom::class)->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('teacher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_assignments');
        Schema::dropIfExists('teachers');
    }
};
