<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Lets clients safely retry order creation; scoped per user.
            $table->string('idempotency_key')->nullable();
            $table->unique(['user_id', 'idempotency_key']);

            // Set once an order's stock has been given back, so cancel / delete / return
            // approval can never restore the same stock twice.
            $table->timestamp('stock_restored_at')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            // Optimistic locking: bumped on every update.
            $table->unsignedInteger('version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'stock_restored_at']);
        });
    }
};
