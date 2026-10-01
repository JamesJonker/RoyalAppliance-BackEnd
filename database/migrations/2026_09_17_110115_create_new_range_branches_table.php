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
        Schema::create('new_range_branches', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Branch Information
            |--------------------------------------------------------------------------
            */

            $table->string('name')
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Sales Workbook Sheet Name
            |--------------------------------------------------------------------------
            |
            | Examples:
            |
            | Protec     -> Protec Sales
            | China Gate -> China Gate Sales
            | West       -> West Sales
            | Bosworth   -> Bosworth Sales
            | Soweto     -> Soweto Sales
            |
            | This allows the daily sales importer to determine which
            | branch a worksheet belongs to.
            |
            */

            $table->string('sales_sheet_name')
                ->nullable()
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                'is_active',
                'nr_branch_active_idx'
            );
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_branches'
        );
    }
};