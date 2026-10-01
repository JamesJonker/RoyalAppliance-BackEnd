<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->integer('months');
            $table->decimal('monthly_deduction', 10, 2);
            $table->integer('months_remaining');
            $table->date('start_month'); 
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
