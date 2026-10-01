<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {

            $table
                ->string('tax_number')
                ->nullable()
                ->after('Id_number');

            $table
                ->string('passport_number')
                ->nullable()
                ->after('tax_number');

            $table
                ->string('passport_country')
                ->nullable()
                ->after('passport_number');

        });
    }


    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {

            $table->dropColumn([

                'tax_number',
                'passport_number',
                'passport_country',

            ]);

        });
    }
};