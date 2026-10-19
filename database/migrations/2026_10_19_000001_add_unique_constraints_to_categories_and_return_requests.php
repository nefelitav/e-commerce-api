<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unique('name');
        });

        DB::statement("CREATE UNIQUE INDEX return_requests_active_order_unique ON return_requests (order_id) WHERE status <> 'rejected'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX return_requests_active_order_unique');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
