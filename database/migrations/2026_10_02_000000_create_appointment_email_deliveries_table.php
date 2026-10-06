<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_email_deliveries', function (Blueprint $table) {
            $table->string('activity_id', 191)->primary();
            $table->string('recipient_email')->nullable();
            $table->json('appointment_data')->nullable();
            $table->string('claim_token', 64)->nullable();
            $table->string('status', 24)->default('queued')->index();
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_email_deliveries');
    }
};
