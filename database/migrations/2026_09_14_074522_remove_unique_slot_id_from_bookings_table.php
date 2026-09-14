<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('slot_id');
            $table->dropUnique('bookings_slot_id_status_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unique(['slot_id', 'status'], 'bookings_slot_id_status_unique');
            $table->dropIndex(['slot_id']);
        });
    }
};
