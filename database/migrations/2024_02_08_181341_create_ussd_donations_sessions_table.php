<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ussd_donations_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->string('mobile');
            $table->string('platform');
            $table->string('message');
            $table->string('service_code');
            $table->string('operator');
            $table->string('donation_amount');
            $table->string('type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ussd_donations_sessions');
    }
};
