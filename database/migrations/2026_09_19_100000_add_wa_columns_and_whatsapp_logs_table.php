<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('phone_wa')->nullable()->after('phone');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->date('as_of_date')->nullable()->after('period');
        });

        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('recipient')->nullable();
            $table->text('body_preview')->nullable();
            $table->enum('status', ['SENT', 'FAILED', 'QUEUED', 'SKIPPED'])->default('SENT');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn('as_of_date');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('phone_wa');
        });
    }
};