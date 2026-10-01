<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table) {

            $table->id();

            $table->foreignId('loan_id')
                ->constrained('loans')
                ->onDelete('cascade');

            $table->integer('month_number');

            $table->decimal(
                'deduction_amount',
                10,
                2
            );

            $table->enum(
                'status',
                [
                    'pending',
                    'paid',
                    'partial'
                ]
            )->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};