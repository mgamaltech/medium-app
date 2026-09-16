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
        // 1. جعل cover_image غير قابل للفراغ (NOT NULL)
        Schema::table('articles', function (Blueprint $table) {
            $table->string('cover_image')->nullable(false)->change();
        });

        // 2. إضافة Foreign Key مع cascadeOnDelete لجدول subscriptions
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        // 3. إضافة Foreign Key مع cascadeOnDelete لجدول subscription_items
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->cascadeOnDelete();
        });

        // 4. إضافة Index عادي على resource_id في جدول slots
        Schema::table('slots', function (Blueprint $table) {
            $table->index('resource_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->dropIndex(['resource_id']);
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('cover_image')->nullable()->change();
        });
    }
};
