<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {

            /*
             * How the leave entitlement is calculated.
             *
             * MONTHLY
             *   Annual leave type.
             *
             * SICK_36_MONTH
             *   BCEA sick leave.
             *
             * FIXED_ANNUAL
             *   Family responsibility leave.
             *
             * NONE
             *   Unpaid leave.
             */
            $table->string('calculation_method')
                ->default('MONTHLY')
                ->after('code');

            /*
             * Number of months in the entitlement cycle.
             *
             * Annual / Family = 12
             * Sick = 36
             * Unpaid = 0
             */
            $table->unsignedInteger('cycle_months')
                ->default(12)
                ->after('calculation_method');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn([
                'calculation_method',
                'cycle_months',
            ]);
        });
    }
};