<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployerTaxSetting extends Model
{
    use HasFactory;

    protected $fillable = [

        'company_name',
        'trading_name',
        'company_registration_number',

        'paye_reference',
        'sdl_reference',
        'uif_reference',
        'vat_number',

        'contact_person',
        'contact_email',
        'contact_number',

        'physical_unit',
        'physical_complex',
        'physical_street',
        'physical_suburb',
        'physical_city',
        'physical_province',
        'physical_postal_code',

        'postal_same_as_physical',

        'postal_line_1',
        'postal_line_2',
        'postal_suburb',
        'postal_city',
        'postal_province',
        'postal_code',

        'is_active',

    ];


    protected $casts = [

        'postal_same_as_physical' =>
            'boolean',

        'is_active' =>
            'boolean',

    ];
}