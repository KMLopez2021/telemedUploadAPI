<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('appointment_email_deliveries', 'appointment_id')) {
            return;
        }

        Schema::table('appointment_email_deliveries', function (Blueprint $table) {
            $table->dropPrimary();
            $table->renameColumn('appointment_id', 'activity_id');
        });

        Schema::table('appointment_email_deliveries', function (Blueprint $table) {
            $table->primary('activity_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('appointment_email_deliveries', 'activity_id')) {
            return;
        }

        Schema::table('appointment_email_deliveries', function (Blueprint $table) {
            $table->dropPrimary();
            $table->renameColumn('activity_id', 'appointment_id');
        });

        Schema::table('appointment_email_deliveries', function (Blueprint $table) {
            $table->primary('appointment_id');
        });
    }
};
