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
            $table->dropForeign(['slot_id']);
            $table->dropUnique(['slot_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('slot_id')->references('id')->on('slots')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['slot_id']);
            $table->unique('slot_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreign('slot_id')->references('id')->on('slots')->cascadeOnDelete();
        });
    }
};
