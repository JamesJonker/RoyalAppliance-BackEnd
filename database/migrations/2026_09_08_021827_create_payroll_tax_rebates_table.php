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
        Schema::create('payroll_tax_rebates', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Tax year
            |--------------------------------------------------------------------------
            */

            $table->foreignId('payroll_tax_year_id')
                ->constrained('payroll_tax_years')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Age category
            |--------------------------------------------------------------------------
            |
            | primary   = Under 65
            | secondary = 65 to 74
            | tertiary  = 75+
            |
            */

            $table->string('rebate_type', 20);

            /*
            |--------------------------------------------------------------------------
            | Minimum age
            |--------------------------------------------------------------------------
            */

            $table->unsignedTinyInteger('minimum_age')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Rebate amount
            |--------------------------------------------------------------------------
            */

            $table->decimal('rebate_amount', 15, 2);

            /*
            |--------------------------------------------------------------------------
            | Tax threshold
            |--------------------------------------------------------------------------
            |
            | Annual taxable income below this amount results in no PAYE
            | after applying the applicable rebate.
            |
            */

            $table->decimal('tax_threshold', 15, 2);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Index
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['payroll_tax_year_id', 'rebate_type'],
                'tax_rebates_year_type_idx'
            );

            $table->unique(
                ['payroll_tax_year_id', 'rebate_type'],
                'tax_rebates_year_type_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_tax_rebates');
    }
};
