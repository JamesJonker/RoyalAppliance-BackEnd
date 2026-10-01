<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'new_range_order_items',
            function (Blueprint $table) {

                $table->id();


                $table
                    ->foreignId('order_id')
                    ->constrained(
                        'new_range_orders'
                    )
                    ->cascadeOnDelete();


                $table
                    ->foreignId('product_id')
                    ->constrained(
                        'new_range_products'
                    )
                    ->restrictOnDelete();


                $table
                    ->foreignId('branch_id')
                    ->constrained(
                        'new_range_branches'
                    )
                    ->restrictOnDelete();


                $table
                    ->string('brand');


                $table
                    ->decimal(
                        'system_soh',
                        10,
                        2
                    )
                    ->default(0);


                $table
                    ->decimal(
                        'minimum_quantity',
                        10,
                        2
                    )
                    ->default(0);


                $table
                    ->decimal(
                        'maximum_quantity',
                        10,
                        2
                    )
                    ->default(0);


                $table
                    ->decimal(
                        'suggested_quantity',
                        10,
                        2
                    )
                    ->default(0);


                $table
                    ->decimal(
                        'order_quantity',
                        10,
                        2
                    )
                    ->default(0);


                $table->timestamps();


                $table->unique([
                    'order_id',
                    'product_id',
                    'branch_id',
                ]);

            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_order_items'
        );
    }
};