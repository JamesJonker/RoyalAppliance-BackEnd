<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'new_range_stock_takes',
            function (Blueprint $table) {

                $table->id();

                $table
                    ->foreignId('branch_id')
                    ->constrained('new_range_branches')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table
                    ->string('stock_take_number')
                    ->unique();

                $table->date('stock_take_date');

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
                    ->foreignId('completed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->timestamp('completed_at')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'branch_id',
                    'stock_take_date'
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_stock_takes'
        );
    }
};