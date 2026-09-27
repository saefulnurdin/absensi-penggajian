<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('grace_minutes')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('work_date_overrides', function (Blueprint $table) {
            $table->id();
            $table->date('work_date')->unique();
            $table->boolean('is_work_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('label', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type', 20)->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
        });

        Schema::table('device_logs', function (Blueprint $table) {
            $table->string('power_source', 10)->nullable();
            $table->unsignedTinyInteger('battery_percent')->nullable();
            $table->smallInteger('wifi_rssi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('device_logs', function (Blueprint $table) {
            $table->dropColumn(['power_source', 'battery_percent', 'wifi_rssi']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['employment_type', 'contract_start', 'contract_end']);
        });

        Schema::dropIfExists('work_date_overrides');
        Schema::dropIfExists('shifts');
    }
};
