
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A payroll period represents one company financial/payroll month.
     *
     * IMPORTANT:
     *
     * Our company's financial year starts in March.
     *
     * March     = Financial Month 1
     * April     = Financial Month 2
     * May       = Financial Month 3
     * June      = Financial Month 4
     * July      = Financial Month 5
     * August    = Financial Month 6
     * September = Financial Month 7
     * October   = Financial Month 8
     * November  = Financial Month 9
     * December  = Financial Month 10
     * January   = Financial Month 11
     * February  = Financial Month 12
     *
     * We store BOTH financial and calendar information.
     *
     * Example:
     *
     * September 2026:
     *
     * financial_year  = 2026/2027
     * financial_month = 7
     * calendar_month  = 9
     * calendar_year   = 2026
     */
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Financial Year
            |--------------------------------------------------------------------------
            |
            | The financial year starts in March.
            |
            | Example:
            |
            | March 2026 - February 2027
            |
            | financial_year = 2026/2027
            |
            | We store the starting year as an integer because this makes
            | database searching and reporting much easier.
            |
            */
            $table->unsignedSmallInteger('financial_year_start');

            /*
            |--------------------------------------------------------------------------
            | Financial Month
            |--------------------------------------------------------------------------
            |
            | March = 1
            | April = 2
            | ...
            | February = 12
            |
            */
            $table->unsignedTinyInteger('financial_month');

            /*
            |--------------------------------------------------------------------------
            | Calendar Month
            |--------------------------------------------------------------------------
            |
            | The actual calendar month.
            |
            | January   = 1
            | February  = 2
            | March     = 3
            | ...
            | December  = 12
            |
            */
            $table->unsignedTinyInteger('calendar_month');

            /*
            |--------------------------------------------------------------------------
            | Calendar Year
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | September 2026 = 2026
            |
            */
            $table->unsignedSmallInteger('calendar_year');

            /*
            |--------------------------------------------------------------------------
            | Payroll Period Dates
            |--------------------------------------------------------------------------
            */
            $table->date('period_start');
            $table->date('period_end');

            /*
            |--------------------------------------------------------------------------
            | Pay Date
            |--------------------------------------------------------------------------
            |
            | The date on which employees are scheduled to receive payment.
            |
            | We leave this nullable because the payroll administrator can
            | configure the actual payment date.
            |
            */
            $table->date('pay_date')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Payroll Status
            |--------------------------------------------------------------------------
            |
            | open
            |     Period has been created and can be worked on.
            |
            | processing
            |     Payroll calculation is currently taking place.
            |
            | calculated
            |     Payroll has been calculated but not approved.
            |
            | approved
            |     Payroll has been checked and approved.
            |
            | paid
            |     Payment has been made.
            |
            | closed
            |     Payroll period is permanently closed.
            |
            */
            $table->enum('status', [
                'open',
                'processing',
                'calculated',
                'approved',
                'paid',
                'closed',
            ])->default('open');

            /*
            |--------------------------------------------------------------------------
            | Approval
            |--------------------------------------------------------------------------
            |
            | We store which user approved the payroll and when.
            |
            */
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Payment
            |--------------------------------------------------------------------------
            |
            | We store which user marked the payroll as paid and when.
            |
            */
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Closing
            |--------------------------------------------------------------------------
            |
            | Once closed, the payroll period should no longer be changed.
            |
            */
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */
            $table->text('notes')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Unique Financial Period
            |--------------------------------------------------------------------------
            |
            | There can only be one Financial Month 1 for a particular
            | financial year, one Financial Month 2, etc.
            |
            | Example:
            |
            | 2026 + 1 = March 2026
            | 2026 + 2 = April 2026
            | ...
            | 2026 + 12 = February 2027
            |
            */
            $table->unique(
                ['financial_year_start', 'financial_month'],
                'payroll_periods_financial_year_month_unique'
            );

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index('calendar_year');
            $table->index('calendar_month');
            $table->index('status');
            $table->index('period_start');
            $table->index('period_end');
            $table->index('pay_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
