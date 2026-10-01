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
        Schema::create('payroll_tax_years', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Tax year identification
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | 2026/2027
            |
            */

            $table->string('tax_year', 9)->unique();

            /*
            |--------------------------------------------------------------------------
            | Tax year dates
            |--------------------------------------------------------------------------
            */

            $table->date('starts_on');

            $table->date('ends_on');

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('is_active')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Description
            |--------------------------------------------------------------------------
            */

            $table->text('description')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'starts_on',
                'ends_on'
            ]);

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_tax_years');
    }
};