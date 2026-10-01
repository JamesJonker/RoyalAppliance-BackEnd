<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PayrollTaxYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_year',
        'starts_on',
        'ends_on',
        'is_active',
        'description',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Tax brackets belonging to this tax year.
     */
    public function brackets(): HasMany
    {
        return $this->hasMany(
            PayrollTaxBracket::class,
            'payroll_tax_year_id'
        );
    }

    /**
     * Tax rebates belonging to this tax year.
     */
    public function rebates(): HasMany
    {
        return $this->hasMany(
            PayrollTaxRebate::class,
            'payroll_tax_year_id'
        );
    }

    /**
     * Get the tax year applicable to a specific date.
     */
    public static function forDate(?Carbon $date = null): ?self
    {
        $date = $date ?? Carbon::today();

        return static::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->where('is_active', true)
            ->first();
    }
}