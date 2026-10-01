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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('Id_number');
            $table->string('contact_no');
            $table->string('email')->unique();
            $table->string('position');
            $table->string('employment_type');
            $table->string('branch');
            $table->decimal('salary', 10, 2);
            $table->date('start_date');
            $table->string('street');
            $table->string('complex');
            $table->string('suburb');
            $table->string('postal_code');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
