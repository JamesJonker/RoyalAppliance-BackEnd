<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_tax_settings', function (Blueprint $table) {

            $table->id();

            // Company identity
            $table->string('company_name');
            $table->string('trading_name')->nullable();

            $table->string('company_registration_number')->nullable();

            // Tax registrations
            $table->string('paye_reference')->nullable();
            $table->string('sdl_reference')->nullable();
            $table->string('uif_reference')->nullable();
            $table->string('vat_number')->nullable();

            // Contact
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_number')->nullable();

            // Physical address
            $table->string('physical_unit')->nullable();
            $table->string('physical_complex')->nullable();
            $table->string('physical_street')->nullable();
            $table->string('physical_suburb')->nullable();
            $table->string('physical_city')->nullable();
            $table->string('physical_province')->nullable();
            $table->string('physical_postal_code')->nullable();

            // Postal address
            $table->boolean('postal_same_as_physical')
                ->default(true);

            $table->string('postal_line_1')->nullable();
            $table->string('postal_line_2')->nullable();
            $table->string('postal_suburb')->nullable();
            $table->string('postal_city')->nullable();
            $table->string('postal_province')->nullable();
            $table->string('postal_code')->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'employer_tax_settings'
        );
    }
};