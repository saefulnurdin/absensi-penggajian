<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_index')->unique(); // 0=Minggu .. 6=Sabtu
            $table->string('day_name', 12);                     // Sunday .. Saturday
            $table->boolean('is_work_day')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_days');
    }
};
