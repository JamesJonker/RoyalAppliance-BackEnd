<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A payment item represents ONE employee payment inside
     * ONE payment run.
     *
     * The payment amount comes directly from the approved
     * payroll item's net_salary.
     *
     * Bank details are stored as a snapshot so that future
     * changes to an employee's bank account cannot alter
     * historical payment records.
     */
    public function up(): void
    {
        Schema::create('payment_items', function (Blueprint $table) {

            $table->id();

            /*
            |----------------------------------------------------------------------
            | Payment Run
            |----------------------------------------------------------------------
            */

            $table->foreignId('payment_run_id')
                ->constrained('payment_runs')
                ->cascadeOnDelete();

            /*
            |----------------------------------------------------------------------
            | Payroll Item
            |----------------------------------------------------------------------
            |
            | This is the source of the payment amount.
            |
            | payment_amount MUST originate from:
            |
            | payroll_items.net_salary
            |
            */

            $table->foreignId('payroll_item_id')
                ->constrained('payroll_items')
                ->restrictOnDelete();

            /*
            |----------------------------------------------------------------------
            | Staff
            |----------------------------------------------------------------------
            */

            $table->foreignId('staff_id')
                ->constrained('staff')
                ->restrictOnDelete();

            /*
            |----------------------------------------------------------------------
            | Employee Snapshot
            |----------------------------------------------------------------------
            |
            | These values make the payment record auditable.
            |
            */

            $table->string('employee_number', 100)
                ->nullable();

            $table->string('first_name')
                ->nullable();

            $table->string('last_name')
                ->nullable();

            /*
            |----------------------------------------------------------------------
            | Bank Details Snapshot
            |----------------------------------------------------------------------
            |
            | These are copied from staff_bank_details when the payment
            | item is created.
            |
            */

            $table->string('bank_name')
                ->nullable();

            $table->string('account_holder_name')
                ->nullable();

            /*
            | Account number is deliberately stored as a string.
            */

            $table->string('account_number', 50)
                ->nullable();

            $table->string('account_type', 50)
                ->nullable();

            $table->string('branch_code', 20)
                ->nullable();

            $table->string('payment_method', 20)
                ->nullable();

            /*
            |----------------------------------------------------------------------
            | Payment Amount
            |----------------------------------------------------------------------
            |
            | This is the amount actually intended for payment.
            |
            | It is copied from:
            |
            | payroll_items.net_salary
            |
            | It must NEVER be manually entered by the user.
            |
            */

            $table->decimal('payroll_net_salary', 15, 2)
                ->default(0);

            $table->decimal('payment_amount', 15, 2)
                ->default(0);

            /*
            |----------------------------------------------------------------------
            | Validation Status
            |----------------------------------------------------------------------
            */

            $table->enum('status', [
                'pending',
                'valid',
                'invalid',
                'excluded',
                'paid',
            ])->default('pending');

            /*
            |----------------------------------------------------------------------
            | Validation Information
            |----------------------------------------------------------------------
            */

            $table->text('validation_errors')
                ->nullable();

            $table->timestamp('validated_at')
                ->nullable();

            /*
            |----------------------------------------------------------------------
            | Payment Information
            |----------------------------------------------------------------------
            */

            $table->timestamp('paid_at')
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
            | Prevent Duplicate Employee Payments
            |----------------------------------------------------------------------
            |
            | An employee can only occur once in a payment run.
            |
            */

            $table->unique(
                ['payment_run_id', 'staff_id'],
                'payment_items_run_staff_unique'
            );

            /*
            |----------------------------------------------------------------------
            | Indexes
            |----------------------------------------------------------------------
            */

            $table->index('payroll_item_id');
            $table->index('staff_id');
            $table->index('employee_number');
            $table->index('status');
            $table->index('account_number');
            $table->index('branch_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_items');
    }
};