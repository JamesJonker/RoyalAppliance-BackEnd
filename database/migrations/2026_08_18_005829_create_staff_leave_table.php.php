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
        Schema::create('staff_leave', function (Blueprint $table) {
            $table->id();

            /*
             * Existing staff table.
             */
            $table->foreignId('staff_id')
                ->constrained('staff')
                ->cascadeOnDelete();

            /*
             * Leave type.
             */
            $table->foreignId('leave_type_id')
                ->constrained('leave_types')
                ->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            /*
             * Supports half days and future decimal
             * leave calculations.
             */
            $table->decimal('days_taken', 8, 2);

            /*
             * pending
             * approved
             * rejected
             * cancelled
             */
            $table->string('status')->default('pending');

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            /*
             * Staff/admin user who approved the leave.
             *
             * We are leaving this as a normal unsignedBigInteger
             * for now because I don't want to assume which table
             * your application's users/authentication uses.
             */
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index([
                'staff_id',
                'leave_type_id',
                'status'
            ]);

            $table->index([
                'start_date',
                'end_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_leave');
    }
};