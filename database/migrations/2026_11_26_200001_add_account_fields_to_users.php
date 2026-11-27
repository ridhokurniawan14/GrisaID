<?php

use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type', 10)->default('staff')->after('password')->index(); // staff | teacher | student
            $table->string('username', 30)->nullable()->unique()->after('account_type');      // NISN untuk siswa
            $table->foreignUuid('teacher_id')->nullable()->unique()->after('username')->constrained('teachers')->nullOnDelete();
            $table->foreignIdFor(Student::class, 'student_id')->nullable()->unique()->after('teacher_id')->constrained('students')->nullOnDelete();
            $table->boolean('must_change_password')->default(false)->after('student_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_id');
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropColumn(['account_type', 'username', 'must_change_password']);
        });
    }
};
