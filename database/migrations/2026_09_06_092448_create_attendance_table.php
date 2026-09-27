<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->enum('status', [
                'HADIR',
                'TERLAMBAT',
                'IZIN',
                'ABSENSI TIDAK LENGKAP',
                'TIDAK HADIR',
            ])->default('HADIR');
            $table->unsignedInteger('late_minutes')->nullable();
            $table->enum('source', ['RFID', 'KEYPAD', 'ADMIN'])->default('RFID');
            $table->text('note')->nullable();
            $table->string('edited_by')->nullable();
            $table->string('edit_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date'], 'attendance_unique_per_day');
            $table->index('attendance_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
