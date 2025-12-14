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
        Schema::create('inventory_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->index()->constrained();
            $table->enum('change_type', [
                'addition',
                'removal',
                'sale',
                'return',
                'adjustment',
                'transfer',
            ])->index();
            $table->integer('quantity_changed');
            $table->integer('previous_quantity');
            $table->integer('new_quantity');
            $table->timestamp('created_at')->index()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_history');
    }
};
