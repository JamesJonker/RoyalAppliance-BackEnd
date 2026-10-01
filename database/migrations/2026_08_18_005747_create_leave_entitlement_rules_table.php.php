<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_entitlement_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('leave_type_id')
                ->constrained('leave_types')
                ->cascadeOnDelete();

            $table->unsignedInteger('minimum_service_years');

            $table->unsignedInteger('maximum_service_years')
                ->nullable();

            $table->decimal('days_entitled', 8, 2);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            /*
             * Short custom index name to stay within
             * MySQL's 64-character identifier limit.
             */
            $table->index(
                [
                    'leave_type_id',
                    'minimum_service_years',
                    'maximum_service_years'
                ],
                'leave_rule_service_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_entitlement_rules');
    }
};