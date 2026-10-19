<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Missing: foreign keys / filters used on hot paths.
        Schema::table('return_requests', function (Blueprint $table) {
            // Every customer's list filters by user_id.
            $table->index('user_id');
            // The partial unique index skips rejected rows; this covers lookups and the
            // ON DELETE CASCADE from orders for those too.
            $table->index('order_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            // Subcategory lookups, the parent_id filter and the self-referencing cascade delete.
            $table->index('parent_id');
        });

        // Never used in a WHERE clause; they only slow down writes.
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });

        Schema::table('inventory_history', function (Blueprint $table) {
            $table->dropIndex(['change_type']);
        });

        // Redundant: the (user_id, idempotency_key) unique index already leads with user_id.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('inventory_history', function (Blueprint $table) {
            $table->index('change_type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropIndex(['order_id']);
            $table->dropIndex(['user_id']);
        });
    }
};
