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
        Schema::create('new_range_stock_movements', function (Blueprint $table) {

            $table->id();


            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            $table->foreignId(
                'product_id'
            )
                ->constrained('new_range_products')
                ->cascadeOnUpdate()
                ->restrictOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Branch
            |--------------------------------------------------------------------------
            */

            $table->foreignId(
                'branch_id'
            )
                ->constrained('new_range_branches')
                ->cascadeOnUpdate()
                ->restrictOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Movement Type
            |--------------------------------------------------------------------------
            |
            | Expected values:
            |
            | OPENING
            | GRV
            | IBT_IN
            | IBT_OUT
            | ADJUSTMENT_IN
            | ADJUSTMENT_OUT
            |
            */

            $table->string(
                'movement_type',
                30
            );


            /*
            |--------------------------------------------------------------------------
            | Movement Date
            |--------------------------------------------------------------------------
            */

            $table->date(
                'movement_date'
            );


            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            |
            | Quantity is stored as a positive number.
            |
            | Whether it increases or decreases stock is determined by
            | movement_type.
            |
            | GRV           +
            | OPENING       +
            | IBT_IN        +
            | ADJUSTMENT_IN +
            |
            | IBT_OUT        -
            | ADJUSTMENT_OUT -
            |
            */

            $table->decimal(
                'quantity',
                12,
                2
            );


            /*
            |--------------------------------------------------------------------------
            | Reference Number
            |--------------------------------------------------------------------------
            |
            | Examples:
            |
            | GRV592766
            | IBT292260
            |
            */

            $table->string(
                'reference_number'
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Cost Snapshot
            |--------------------------------------------------------------------------
            |
            | Useful for GRVs and opening stock.
            |
            */

            $table->decimal(
                'unit_cost_excl',
                12,
                2
            )->nullable();

            $table->decimal(
                'unit_cost_incl',
                12,
                2
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | IBT Transfer Group
            |--------------------------------------------------------------------------
            |
            | An IBT creates two movement records.
            |
            | Example:
            |
            | WEST
            | IBT_OUT
            | Qty 3
            |
            | SOWETO
            | IBT_IN
            | Qty 3
            |
            | Both records receive the SAME transfer_group_id.
            |
            */

            $table->uuid(
                'transfer_group_id'
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Created By
            |--------------------------------------------------------------------------
            */

            $table->foreignId(
                'created_by'
            )
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text(
                'notes'
            )->nullable();


            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            |
            | Explicit short names prevent MySQL's 64-character identifier
            | limit from being exceeded.
            |
            */

            $table->index(
                [
                    'product_id',
                    'branch_id',
                    'movement_date'
                ],
                'nr_stock_product_branch_date_idx'
            );

            $table->index(
                'movement_type',
                'nr_stock_type_idx'
            );

            $table->index(
                'reference_number',
                'nr_stock_reference_idx'
            );

            $table->index(
                'transfer_group_id',
                'nr_stock_transfer_idx'
            );

            $table->index(
                'movement_date',
                'nr_stock_date_idx'
            );
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_stock_movements'
        );
    }
};