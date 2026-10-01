<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A Payroll Run represents one calculation run against a
     * particular Payroll Period.
     *
     * Example:
     *
     * September 2026
     * Financial Year: 2026/2027
     * Financial Month: 7
     *
     * Payroll Run #1
     *
     * If payroll needs to be recalculated before approval, another
     * run can be created:
     *
     * Payroll Run #2
     *
     * Only the approved run will ultimately be used for payment.
     */
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Payroll Period
            |--------------------------------------------------------------------------
            |
            | Every payroll run belongs to a payroll period.
            |
            */
            $table->foreignId('payroll_period_id')
                ->constrained('payroll_periods')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Run Number
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | Payroll Run #1
            | Payroll Run #2
            |
            | This allows us to retain the history of recalculations.
            |
            */
            $table->unsignedInteger('run_number');

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | draft
            |     Run has been created but calculation has not started.
            |
            | calculating
            |     Employee payroll calculations are being performed.
            |
            | calculated
            |     All payroll items have been calculated.
            |
            | approved
            |     Payroll has been checked and approved.
            |
            | paid
            |     The payroll payment has been completed.
            |
            | cancelled
            |     This run is no longer valid.
            |
            */
            $table->enum('status', [
                'draft',
                'calculating',
                'calculated',
                'approved',
                'paid',
                'cancelled',
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Employee Count
            |--------------------------------------------------------------------------
            |
            | Number of employees included in this payroll run.
            |
            */
            $table->unsignedInteger('employee_count')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Payroll Totals
            |--------------------------------------------------------------------------
            |
            | These totals are stored on the run so that the payroll
            | summary does not have to be recalculated every time
            | somebody opens the payroll screen.
            |
            */

            // Total gross remuneration
            $table->decimal('total_gross', 15, 2)->default(0);

            // Total PAYE deducted from employees
            $table->decimal('total_paye', 15, 2)->default(0);

            // Total employee UIF contribution
            $table->decimal('total_employee_uif', 15, 2)->default(0);

            // Total employer UIF contribution
            $table->decimal('total_employer_uif', 15, 2)->default(0);

            // Total SDL payable by employer
            $table->decimal('total_sdl', 15, 2)->default(0);

            // Total other deductions
            $table->decimal('total_deductions', 15, 2)->default(0);

            // Total net salaries payable to employees
            $table->decimal('total_net', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Employer Cost
            |--------------------------------------------------------------------------
            |
            | Employer cost is:
            |
            | Gross remuneration
            | + Employer UIF
            | + SDL
            |
            | Other employer costs can be added later if required.
            |
            */
            $table->decimal('total_employer_cost', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Calculation Information
            |--------------------------------------------------------------------------
            */

            $table->timestamp('calculation_started_at')->nullable();

            $table->timestamp('calculation_completed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Approval
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('approved_by')->nullable();

            $table->timestamp('approved_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Payment
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('paid_by')->nullable();

            $table->timestamp('paid_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cancellation
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('cancelled_by')->nullable();

            $table->timestamp('cancelled_at')->nullable();

            $table->text('cancellation_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */

            $table->text('notes')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Unique Run Number
            |--------------------------------------------------------------------------
            |
            | Run #1 is unique within a payroll period.
            |
            | September 2026:
            |
            | Run #1
            | Run #2
            | Run #3
            |
            */
            $table->unique(
                ['payroll_period_id', 'run_number'],
                'payroll_runs_period_run_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('status');
            $table->index('approved_at');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
