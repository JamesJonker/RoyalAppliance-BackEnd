<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('new_range_ibts', function (Blueprint $table) {

            $table->id();

            $table
                ->uuid('transfer_group_id')
                ->unique();

            $table
                ->string('reference')
                ->unique();

            $table
                ->foreignId('from_branch_id')
                ->constrained(
                    'new_range_branches'
                );

            $table
                ->foreignId('to_branch_id')
                ->constrained(
                    'new_range_branches'
                );

            $table->date(
                'transfer_date'
            );

            $table
                ->string('status')
                ->default('OUT');

            $table
                ->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->timestamp('sent_at')
                ->nullable();

            $table
                ->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->timestamp('received_at')
                ->nullable();

            $table
                ->text('notes')
                ->nullable();

            $table->timestamps();

            $table->index(
                'status'
            );

            $table->index(
                'from_branch_id'
            );

            $table->index(
                'to_branch_id'
            );
        });
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'new_range_ibts'
        );
    }
};