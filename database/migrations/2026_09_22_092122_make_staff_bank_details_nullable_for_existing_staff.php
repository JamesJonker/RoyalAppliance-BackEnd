<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_bank_details', function (Blueprint $table) {

            $table->string('bank_name')->nullable()->change();

            $table->string('account_holder_name')->nullable()->change();

            $table->string('account_number', 50)->nullable()->change();

            $table->string('account_type', 50)->nullable()->change();

            $table->string('branch_code', 20)->nullable()->change();

            $table->string('payment_method', 20)
                ->nullable()
                ->default('EFT')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('staff_bank_details', function (Blueprint $table) {

            $table->string('bank_name')->nullable(false)->change();

            $table->string('account_holder_name')->nullable(false)->change();

            $table->string('account_number', 50)->nullable(false)->change();

            $table->string('account_type', 50)->nullable(false)->change();

            $table->string('branch_code', 20)->nullable(false)->change();

            $table->string('payment_method', 20)
                ->nullable(false)
                ->default('EFT')
                ->change();
        });
    }
};