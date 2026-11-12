<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('major_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('grade')->index();       // 10 | 11 | 12
            $table->string('name', 50);                          // "X KULINER 1" (sama dengan nama rombel Dapodik)
            $table->unsignedTinyInteger('group_no')->nullable(); // 1, 2, 3 (null untuk "X AKL")
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academic_year_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
