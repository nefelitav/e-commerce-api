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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->index()->unique();
            $table->text('description')->nullable();
            $table->string('discount_type');
            $table->decimal('amount', 10, 2);
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->integer('usage_limit')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
