<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'new_range_minimum_stock_levels',
            function (Blueprint $table) {

                $table
                    ->unsignedInteger('maximum_quantity')
                    ->nullable()
                    ->after('minimum_quantity');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'new_range_minimum_stock_levels',
            function (Blueprint $table) {

                $table->dropColumn(
                    'maximum_quantity'
                );
            }
        );
    }
};