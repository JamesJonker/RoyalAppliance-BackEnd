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
        Schema::create('payroll_tax_brackets', function (Blueprint $table) {
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
            | Tax bracket limits
            |--------------------------------------------------------------------------
            */

            $table->decimal('lower_limit', 15, 2);

            $table->decimal('upper_limit', 15, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Tax calculation
            |--------------------------------------------------------------------------
            */

            $table->decimal('base_tax', 15, 2)->default(0);

            $table->decimal('marginal_rate', 8, 6);

            $table->decimal('threshold', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Bracket order
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('bracket_order');

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Index
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['payroll_tax_year_id', 'bracket_order'],
                'tax_brackets_year_order_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_tax_brackets');
    }
};