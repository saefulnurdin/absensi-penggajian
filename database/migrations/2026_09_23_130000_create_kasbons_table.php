<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasbons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->string('deduct_period', 7)->comment('Periode (YYYY-MM) pemotongan dari gaji');
            $table->enum('status', ['PENDING', 'APPROVED', 'CANCELLED', 'PAID'])->default('PENDING');
            $table->timestamp('approved_at')->nullable();
            $table->string('paid_in_period', 7)->nullable()->comment('Periode saat sudah dipotong dari gaji');
            $table->timestamps();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('kasbon_deduction', 12, 2)->default(0)->after('deduction');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('kasbon_deduction');
        });

        Schema::dropIfExists('kasbons');
    }
};