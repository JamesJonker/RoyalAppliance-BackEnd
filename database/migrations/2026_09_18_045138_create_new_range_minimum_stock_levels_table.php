<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'new_range_minimum_stock_levels',
            function (Blueprint $table) {

                $table->id();

                $table
                    ->foreignId('product_id')
                    ->constrained('new_range_products')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('branch_id')
                    ->constrained('new_range_branches')
                    ->cascadeOnDelete();

                $table
                    ->decimal(
                        'minimum_quantity',
                        10,
                        2
                    )
                    ->default(0);

                $table->timestamps();

                $table->unique(
                    [
                        'product_id',
                        'branch_id'
                    ],
                    'new_range_min_stock_product_branch_unique'
                );

                $table->index(
                    'product_id'
                );

                $table->index(
                    'branch_id'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_minimum_stock_levels'
        );
    }
};