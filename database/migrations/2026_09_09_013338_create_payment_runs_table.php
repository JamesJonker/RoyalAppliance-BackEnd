<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A payment run represents one batch of employee payments
     * created from an approved payroll run.
     *
     * Example:
     *
     * Payroll Run #1
     *       ↓
     * Payment Run #1
     *
     * The payment run is separate from payroll because payroll
     * calculation and bank payment are two different processes.
     */
    public function up(): void
    {
        Schema::create('payment_runs', function (Blueprint $table) {

            $table->id();

            /*
            |----------------------------------------------------------------------
            | Payroll Run
            |----------------------------------------------------------------------
            |
            | Every payment run must originate from a payroll run.
            |
            */

            $table->foreignId('payroll_run_id')
                ->constrained('payroll_runs')
                ->restrictOnDelete();

            /*
            |----------------------------------------------------------------------
            | Payment Run Number
            |----------------------------------------------------------------------
            |
            | Allows more than one payment attempt/history for a payroll run.
            |
            | Example:
            |
            | Payroll Run #1
            |     Payment Run #1
            |
            | If cancelled:
            |
            | Payroll Run #1
            |     Payment Run #2
            |
            */

            $table->unsignedInteger('run_number');

            /*
            |----------------------------------------------------------------------
            | Status
            |----------------------------------------------------------------------
            |
            | draft
            |     Payment run has been created.
            |
            | validating
            |     Bank details are being checked.
            |
            | validated
            |     All payment items have passed validation.
            |
            | approved
            |     Payment run has been approved for bank submission.
            |
            | exported
            |     Standard Bank CSV has been generated.
            |
            | paid
            |     Payment has been confirmed as completed.
            |
            | cancelled
            |     Payment run has been cancelled.
            |
            */

            $table->enum('status', [
                'draft',
                'validating',
                'validated',
                'approved',
                'exported',
                'paid',
                'cancelled',
            ])->default('draft');

            /*
            |----------------------------------------------------------------------
            | Payment Information
            |----------------------------------------------------------------------
            */

            $table->unsignedInteger('employee_count')
                ->default(0);

            /*
            | Total amount that will be paid to employees.
            |
            | This must equal the sum of payment_items.payment_amount.
            */

            $table->decimal('total_amount', 15, 2)
                ->default(0);

            /*
            |----------------------------------------------------------------------
            | Validation
            |----------------------------------------------------------------------
            */

            $table->unsignedInteger('valid_count')
                ->default(0);

            $table->unsignedInteger('invalid_count')
                ->default(0);

            $table->timestamp('validation_started_at')
                ->nullable();

            $table->timestamp('validation_completed_at')
                ->nullable();

            /*
            |----------------------------------------------------------------------
            | Approval
            |----------------------------------------------------------------------
            */

            $table->unsignedBigInteger('approved_by')
                ->nullable();

            $table->timestamp('approved_at')
                ->nullable();

            /*
            |----------------------------------------------------------------------
            | Export
            |----------------------------------------------------------------------
            |
            | Information about the bank file generated for this payment run.
            |
            */

            $table->string('export_file_name')
                ->nullable();

            $table->timestamp('exported_at')
                ->nullable();

            $table->unsignedBigInteger('exported_by')
                ->nullable();

            /*
            |----------------------------------------------------------------------
            | Payment
            |----------------------------------------------------------------------
            */

            $table->unsignedBigInteger('paid_by')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            /*
            |----------------------------------------------------------------------
            | Cancellation
            |----------------------------------------------------------------------
            */

            $table->unsignedBigInteger('cancelled_by')
                ->nullable();

            $table->timestamp('cancelled_at')
                ->nullable();

            $table->text('cancellation_reason')
                ->nullable();

            /*
            |----------------------------------------------------------------------
            | Notes
            |----------------------------------------------------------------------
            */

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            /*
            |----------------------------------------------------------------------
            | Unique Payment Run Number
            |----------------------------------------------------------------------
            */

            $table->unique(
                ['payroll_run_id', 'run_number'],
                'payment_runs_payroll_run_number_unique'
            );

            /*
            |----------------------------------------------------------------------
            | Indexes
            |----------------------------------------------------------------------
            */

            $table->index('status');
            $table->index('approved_at');
            $table->index('exported_at');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_runs');
    }
};