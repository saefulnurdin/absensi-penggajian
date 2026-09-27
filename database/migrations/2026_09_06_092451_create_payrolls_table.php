<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM misal 2026-09
            $table->unsignedInteger('work_days')->default(0);
            $table->unsignedInteger('hadir_count')->default(0);
            $table->unsignedInteger('late_count')->default(0);
            $table->unsignedInteger('izin_count')->default(0);
            $table->unsignedInteger('absent_count')->default(0);
            $table->unsignedInteger('incomplete_count')->default(0);
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('daily_salary', 12, 2)->default(0);
            $table->decimal('deduction', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            $table->enum('status', ['DRAFT', 'DIPROSES'])->default('DRAFT');
            $table->timestamps();

            $table->unique(['employee_id', 'period'], 'payroll_unique_employee_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
