<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {

            if (!Schema::hasColumn('payslips', 'is_prorated')) {
                $table->boolean('is_prorated')
                    ->default(false);
            }

            if (!Schema::hasColumn('payslips', 'termination_date')) {
                $table->date('termination_date')
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'days_worked')) {
                $table->decimal('days_worked', 8, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'daily_rate')) {
                $table->decimal('daily_rate', 10, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'worked_gross')) {
                $table->decimal('worked_gross', 10, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'leave_days_due')) {
                $table->decimal('leave_days_due', 8, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'leave_daily_rate')) {
                $table->decimal('leave_daily_rate', 10, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'gross_leave_pay')) {
                $table->decimal('gross_leave_pay', 10, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'leave_paye')) {
                $table->decimal('leave_paye', 10, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'leave_uif')) {
                $table->decimal('leave_uif', 10, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'net_leave_pay')) {
                $table->decimal('net_leave_pay', 10, 2)
                    ->nullable();
            }

            if (!Schema::hasColumn('payslips', 'gross_remuneration')) {
                $table->decimal('gross_remuneration', 10, 2)
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {

            $columns = [
                'termination_date',
                'days_worked',
                'daily_rate',
                'worked_gross',
                'leave_days_due',
                'leave_daily_rate',
                'gross_leave_pay',
                'leave_paye',
                'leave_uif',
                'net_leave_pay',
                'gross_remuneration',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('payslips', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};