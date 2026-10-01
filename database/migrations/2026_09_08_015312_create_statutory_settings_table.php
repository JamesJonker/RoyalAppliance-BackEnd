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
        Schema::create('statutory_settings', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Setting identification
            |--------------------------------------------------------------------------
            */

            $table->string('setting_key')->index();

            $table->string('setting_name');

            $table->string('category')->index();

            /*
            |--------------------------------------------------------------------------
            | Setting value
            |--------------------------------------------------------------------------
            |
            | Values are stored as strings so that the same table can handle:
            |
            | percentages
            | amounts
            | tax years
            | booleans
            | ceilings
            | other statutory values
            |
            */

            $table->string('value');

            $table->string('value_type')->default('decimal');

            /*
            |--------------------------------------------------------------------------
            | Effective dates
            |--------------------------------------------------------------------------
            |
            | This allows statutory rules to change without destroying
            | historical payroll calculations.
            |
            */

            $table->date('effective_from');

            $table->date('effective_to')->nullable();

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
                'setting_key',
                'effective_from',
                'effective_to'
            ]);

            $table->index([
                'category',
                'is_active'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statutory_settings');
    }
};