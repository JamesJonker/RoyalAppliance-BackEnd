<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'new_range_stock_take_items',
            function (Blueprint $table) {

                $table->id();

                $table
                    ->foreignId('stock_take_id')
                    ->constrained(
                        'new_range_stock_takes'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId('product_id')
                    ->constrained(
                        'new_range_products'
                    )
                    ->restrictOnDelete();

                /*
                |--------------------------------------------------------------------------
                | SYSTEM SOH SNAPSHOT
                |--------------------------------------------------------------------------
                |
                | This is the system stock quantity at the moment
                | the stock take is started.
                |
                */

                $table
                    ->decimal(
                        'system_quantity',
                        10,
                        2
                    )
                    ->default(0);

                /*
                |--------------------------------------------------------------------------
                | PHYSICAL COUNT
                |--------------------------------------------------------------------------
                */

                $table
                    ->decimal(
                        'physical_quantity',
                        10,
                        2
                    )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | VARIANCE
                |--------------------------------------------------------------------------
                |
                | physical_quantity - system_quantity
                |
                */

                $table
                    ->decimal(
                        'variance_quantity',
                        10,
                        2
                    )
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'stock_take_id',
                    'product_id'
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_stock_take_items'
        );
    }
};