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
        Schema::create('staff_bank_details', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Staff
            |--------------------------------------------------------------------------
            */

            $table->foreignId('staff_id')
                ->unique()
                ->constrained('staff')
                ->cascadeOnDelete();


            /*
            |--------------------------------------------------------------------------
            | Banking details
            |--------------------------------------------------------------------------
            */

            $table->string('bank_name');

            $table->string('account_holder_name');

            /*
             * Keep account numbers as strings.
             *
             * This prevents leading zeroes from being lost.
             */

            $table->string('account_number', 50);

            $table->string('account_type', 50);

            $table->string('branch_code', 20);


            /*
            |--------------------------------------------------------------------------
            | Payment method
            |--------------------------------------------------------------------------
            */

            $table->string('payment_method', 20)
                ->default('EFT');


            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'staff_bank_details'
        );
    }
};