<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {

            $table->boolean('is_prorated')
                ->default(false)
                ->after('year');

            $table->decimal('days_worked', 8, 2)
                ->nullable()
                ->after('is_prorated');

            $table->decimal('daily_rate', 10, 2)
                ->nullable()
                ->after('days_worked');

            $table->decimal('worked_gross', 10, 2)
                ->nullable()
                ->after('daily_rate');

            $table->decimal('leave_days_due', 8, 2)
                ->nullable()
                ->after('worked_gross');

            $table->decimal('leave_daily_rate', 10, 2)
                ->nullable()
                ->after('leave_days_due');

            $table->decimal('gross_leave_pay', 10, 2)
                ->nullable()
                ->after('leave_daily_rate');

            $table->decimal('gross_remuneration', 10, 2)
                ->nullable()
                ->after('gross_leave_pay');

            $table->decimal('net_leave_pay', 10, 2)
                ->nullable()
                ->after('gross_remuneration');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {

            $table->dropColumn([
                'is_prorated',
                'days_worked',
                'daily_rate',
                'worked_gross',
                'leave_days_due',
                'leave_daily_rate',
                'gross_leave_pay',
                'gross_remuneration',
                'net_leave_pay',
            ]);
        });
    }
};