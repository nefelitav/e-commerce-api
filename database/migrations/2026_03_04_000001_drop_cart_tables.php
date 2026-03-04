<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }

    public function down(): void
    {
        Schema::create('carts', function ($table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained();
            $table->timestamps();
        });

        Schema::create('cart_items', function ($table) {
            $table->id();
            $table->foreignId('cart_id')->index()->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->index()->constrained();
            $table->integer('quantity');
            $table->timestamps();
        });
    }
};

