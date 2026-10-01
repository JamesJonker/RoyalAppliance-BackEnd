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
        Schema::table('staff', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | Historical Staff Import
            |--------------------------------------------------------------------------
            |
            | These fields are nullable at database level because existing employees
            | may not have all information available during the migration.
            |
            | The normal Create Staff API can still require these fields through
            | Laravel validation.
            |
            */

            $table->string('Id_number')->nullable()->change();

            $table->string('contact_no')->nullable()->change();

            $table->string('email')->nullable()->change();

            $table->string('position')->nullable()->change();

            $table->string('employment_type')->nullable()->change();

            $table->date('start_date')->nullable()->change();

            $table->string('street')->nullable()->change();

            $table->string('complex')->nullable()->change();

            $table->string('suburb')->nullable()->change();

            $table->string('postal_code')->nullable()->change();

            $table->decimal('salary', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {

            $table->string('Id_number')->nullable(false)->change();

            $table->string('contact_no')->nullable(false)->change();

            $table->string('email')->nullable(false)->change();

            $table->string('position')->nullable(false)->change();

            $table->string('employment_type')->nullable(false)->change();

            $table->date('start_date')->nullable(false)->change();

            $table->string('street')->nullable(false)->change();

            $table->string('complex')->nullable(false)->change();

            $table->string('suburb')->nullable(false)->change();

            $table->string('postal_code')->nullable(false)->change();

            $table->decimal('salary', 10, 2)->nullable(false)->change();
        });
    }
};