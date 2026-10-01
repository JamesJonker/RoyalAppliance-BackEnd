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
        Schema::create('new_range_products', function (Blueprint $table) {

            $table->id();


            /*
            |--------------------------------------------------------------------------
            | Product Information
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | DAC363-NEW
            |
            | The sales workbook may contain:
            |
            | DAC363-NEW-01
            |
            | The importer will remove the -01 before matching.
            |
            */

            $table->string('model_number')
                ->unique();

            $table->string('description')
                ->nullable();


            /*
            |--------------------------------------------------------------------------
            | Current Cost
            |--------------------------------------------------------------------------
            |
            | These are the CURRENT product costs.
            |
            | Historical sales will store their own cost snapshot so changing
            | this value later will NOT change previous months' GP.
            |
            */

            $table->decimal(
                'cost_excl',
                12,
                2
            )->default(0);

            $table->decimal(
                'cost_incl',
                12,
                2
            )->default(0);


            /*
            |--------------------------------------------------------------------------
            | Current Retail Price
            |--------------------------------------------------------------------------
            |
            | User-changeable selling price including VAT.
            |
            */

            $table->decimal(
                'retail_incl',
                12,
                2
            )->default(0);


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
            |
            | model_number already receives a UNIQUE index above, so we do not
            | need to create another normal index for it.
            |
            */

            $table->index(
                'is_active',
                'nr_product_active_idx'
            );
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_products'
        );
    }
};