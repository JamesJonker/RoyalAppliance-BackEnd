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
        Schema::create('new_range_sales', function (Blueprint $table) {

            $table->id();


            /*
            |--------------------------------------------------------------------------
            | Sales Import
            |--------------------------------------------------------------------------
            |
            | Identifies which morning workbook created this sale.
            |
            */

            $table->foreignId(
                'sales_import_id'
            )
                ->constrained('new_range_sales_imports')
                ->cascadeOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            $table->foreignId(
                'product_id'
            )
                ->constrained('new_range_products')
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
                ->restrictOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Sale Date
            |--------------------------------------------------------------------------
            */

            $table->date(
                'sale_date'
            );


            /*
            |--------------------------------------------------------------------------
            | Original PLU
            |--------------------------------------------------------------------------
            |
            | The workbook may contain:
            |
            | DAC363-NEW-01
            |
            | The product master contains:
            |
            | DAC363-NEW
            |
            | We retain the original PLU for audit purposes.
            |
            */

            $table->string(
                'original_plu'
            );


            /*
            |--------------------------------------------------------------------------
            | Description
            |--------------------------------------------------------------------------
            */

            $table->string(
                'description'
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Quantity Sold
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'quantity',
                12,
                2
            );


            /*
            |--------------------------------------------------------------------------
            | Actual Sales Values
            |--------------------------------------------------------------------------
            |
            | These come directly from the morning sales workbook.
            |
            */

            $table->decimal(
                'sales_excl',
                14,
                2
            );

            $table->decimal(
                'sales_incl',
                14,
                2
            );


            /*
            |--------------------------------------------------------------------------
            | Cost Snapshot
            |--------------------------------------------------------------------------
            |
            | The product cost is copied into the sale when it is imported.
            |
            | This means changing today's product cost will NOT alter
            | September's GP later.
            |
            */

            $table->decimal(
                'unit_cost_excl',
                12,
                2
            );

            $table->decimal(
                'total_cost_excl',
                14,
                2
            );


            /*
            |--------------------------------------------------------------------------
            | Gross Profit
            |--------------------------------------------------------------------------
            |
            | gross_profit =
            |
            | sales_excl - total_cost_excl
            |
            */

            $table->decimal(
                'gross_profit',
                14,
                2
            );


            /*
            |--------------------------------------------------------------------------
            | GP Percentage
            |--------------------------------------------------------------------------
            |
            | GP % =
            |
            | gross_profit / sales_excl * 100
            |
            */

            $table->decimal(
                'gp_percentage',
                8,
                4
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Day Period
            |--------------------------------------------------------------------------
            |
            | Retained from the source workbook where available.
            |
            */

            $table->string(
                'day_period'
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Source Sheet
            |--------------------------------------------------------------------------
            |
            | Examples:
            |
            | West Sales
            | Soweto Sales
            | China Gate Sales
            |
            */

            $table->string(
                'source_sheet'
            )->nullable();


            /*
            |--------------------------------------------------------------------------
            | Source Row
            |--------------------------------------------------------------------------
            |
            | Allows us to trace an imported record back to the original
            | Excel row if something needs investigating.
            |
            */

            $table->unsignedInteger(
                'source_row'
            )->nullable();


            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Reporting Indexes
            |--------------------------------------------------------------------------
            |
            | Short explicit names avoid MySQL's identifier length limit.
            |
            */

            $table->index(
                [
                    'sale_date',
                    'branch_id'
                ],
                'nr_sales_date_branch_idx'
            );

            $table->index(
                [
                    'product_id',
                    'sale_date'
                ],
                'nr_sales_product_date_idx'
            );

            $table->index(
                [
                    'branch_id',
                    'product_id',
                    'sale_date'
                ],
                'nr_sales_branch_product_date_idx'
            );

            $table->index(
                'original_plu',
                'nr_sales_plu_idx'
            );
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_sales'
        );
    }
};