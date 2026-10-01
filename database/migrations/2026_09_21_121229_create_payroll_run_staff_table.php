<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_staff', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_run_id')
                ->constrained('payroll_runs')
                ->cascadeOnDelete();

            $table->foreignId('staff_id')
                ->constrained('staff')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'payroll_run_id',
                'staff_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_staff');
    }
};
