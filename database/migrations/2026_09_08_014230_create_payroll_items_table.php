<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A payroll item represents ONE employee inside ONE payroll run.
     *
     * Example:
     *
     * Payroll Run #1
     *     |
     *     +-- Employee 1001
     *     +-- Employee 1002
     *     +-- Employee 1003
     *
     * Each employee gets one payroll item.
     */
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Payroll Run
            |--------------------------------------------------------------------------
            */

            $table->foreignId('payroll_run_id')
                ->constrained('payroll_runs')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Employee
            |--------------------------------------------------------------------------
            |
            | We retain the staff relationship, but also store a snapshot of
            | important employee information below.
            |
            */

            $table->foreignId('staff_id')
                ->constrained('staff')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Employee Snapshot
            |--------------------------------------------------------------------------
            |
            | These values represent what the employee looked like when this
            | payroll was calculated.
            |
            | This is important for payroll history.
            |
            | If an employee changes department, salary or bank information
            | later, the historical payroll must not change.
            |
            */

            $table->string('employee_number', 100)->nullable();

            $table->string('first_name')->nullable();

            $table->string('last_name')->nullable();

            $table->string('id_number', 50)->nullable();

            $table->string('tax_number', 50)->nullable();

            $table->string('employment_type', 100)->nullable();

            $table->string('position')->nullable();

            $table->string('branch')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Employment Information
            |--------------------------------------------------------------------------
            */

            $table->date('start_date')->nullable();

            $table->date('termination_date')->nullable();

            $table->string('termination_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Working Time
            |--------------------------------------------------------------------------
            */

            $table->decimal('hours_worked', 8, 2)->default(0);

            $table->decimal('ordinary_hours', 8, 2)->default(0);

            $table->decimal('overtime_hours', 8, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Basic Earnings
            |--------------------------------------------------------------------------
            */

            $table->decimal('basic_salary', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Additional Earnings
            |--------------------------------------------------------------------------
            |
            | These are deliberately separated.
            |
            | Later we can add configurable earning types without changing
            | the basic payroll structure.
            |
            */

            $table->decimal('overtime_amount', 15, 2)->default(0);

            $table->decimal('allowances', 15, 2)->default(0);

            $table->decimal('bonus', 15, 2)->default(0);

            $table->decimal('commission', 15, 2)->default(0);

            $table->decimal('other_earnings', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Gross Salary
            |--------------------------------------------------------------------------
            |
            | Gross =
            |
            | Basic Salary
            | + Overtime
            | + Allowances
            | + Bonus
            | + Commission
            | + Other Earnings
            |
            */

            $table->decimal('gross_salary', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | UIF
            |--------------------------------------------------------------------------
            */

            /*
            | Amount of remuneration subject to UIF.
            */
            $table->decimal('uif_liable_amount', 15, 2)->default(0);

            /*
            | Employee UIF contribution.
            */
            $table->decimal('employee_uif', 15, 2)->default(0);

            /*
            | Employer UIF contribution.
            */
            $table->decimal('employer_uif', 15, 2)->default(0);

            /*
            | Indicates whether this employee is liable for UIF.
            */
            $table->boolean('uif_liable')->default(true);

            /*
            | UIF exclusion/contribution code where applicable.
            */
            $table->string('uif_exclusion_code', 50)->nullable();

            /*
            |--------------------------------------------------------------------------
            | PAYE
            |--------------------------------------------------------------------------
            */

            $table->decimal('taxable_income', 15, 2)->default(0);

            $table->decimal('paye', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | SDL
            |--------------------------------------------------------------------------
            |
            | SDL is an employer liability, not an employee deduction.
            |
            */

            $table->decimal('sdl_liable_amount', 15, 2)->default(0);

            $table->decimal('sdl', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Deductions
            |--------------------------------------------------------------------------
            */

            /*
            | Loan deductions from the employee.
            */
            $table->decimal('loan_deduction', 15, 2)->default(0);

            /*
            | Unpaid leave or other leave-related payroll deductions.
            */
            $table->decimal('leave_deduction', 15, 2)->default(0);

            /*
            | Other employee deductions.
            */
            $table->decimal('other_deductions', 15, 2)->default(0);

            /*
            | Total employee deductions.
            |
            | This includes:
            |
            | PAYE
            | UIF
            | Loans
            | Leave deductions
            | Other deductions
            |
            */
            $table->decimal('total_deductions', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Net Salary
            |--------------------------------------------------------------------------
            |
            | Amount actually payable to the employee.
            |
            */

            $table->decimal('net_salary', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Employer Cost
            |--------------------------------------------------------------------------
            |
            | Gross salary
            | + Employer UIF
            | + SDL
            |
            */

            $table->decimal('employer_cost', 15, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Leave Information
            |--------------------------------------------------------------------------
            |
            | These fields provide a snapshot for the payroll/payslip.
            |
            */

            $table->decimal('annual_leave_taken', 8, 2)->default(0);

            $table->decimal('annual_leave_balance', 8, 2)->default(0);

            $table->decimal('sick_leave_taken', 8, 2)->default(0);

            $table->decimal('sick_leave_balance', 8, 2)->default(0);

            $table->decimal('family_leave_taken', 8, 2)->default(0);

            $table->decimal('family_leave_balance', 8, 2)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Calculation Snapshot
            |--------------------------------------------------------------------------
            |
            | This JSON column will contain the inputs used during calculation.
            |
            | Example:
            |
            | {
            |     "basic_salary": 25000,
            |     "uif_rate": 0.01,
            |     "uif_ceiling": 17712,
            |     "tax_year": "2026/2027"
            | }
            |
            | This gives us an audit trail of how the payroll was calculated.
            |
            */

            $table->json('calculation_data')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | calculated
            | excluded
            |
            | An employee may be excluded from a payroll run, for example,
            | if they are not active during the period.
            |
            */

            $table->enum('status', [
                'calculated',
                'excluded',
            ])->default('calculated');

            $table->text('exclusion_reason')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Employees
            |--------------------------------------------------------------------------
            |
            | One employee can only occur once in a particular payroll run.
            |
            */

            $table->unique(
                ['payroll_run_id', 'staff_id'],
                'payroll_items_run_staff_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('staff_id');

            $table->index('employee_number');

            $table->index('status');

            $table->index('uif_liable');

            $table->index('tax_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};