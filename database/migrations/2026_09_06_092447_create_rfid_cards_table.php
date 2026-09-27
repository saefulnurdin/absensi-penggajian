<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('uid', 32)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();

            // Satu kartu aktif per pegawai (hubungan 1-1).
            $table->unique(['employee_id', 'is_active'], 'rfid_active_per_employee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_cards');
    }
};
