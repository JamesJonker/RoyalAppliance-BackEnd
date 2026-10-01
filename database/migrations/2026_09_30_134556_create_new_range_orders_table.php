<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'new_range_orders',
            function (Blueprint $table) {

                $table->id();

                $table
                    ->string('order_number')
                    ->unique();

                $table->date(
                    'order_date'
                );

                $table
                    ->string('status')
                    ->default('draft');

                $table
                    ->text('notes')
                    ->nullable();

                $table
                    ->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->timestamp('exported_at')
                    ->nullable();

                $table->timestamps();

            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_orders'
        );
    }
};