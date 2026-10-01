<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class StatutorySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_key',
        'setting_name',
        'category',
        'value',
        'value_type',
        'effective_from',
        'effective_to',
        'is_active',
        'description',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get a statutory setting that applies on a specific date.
     */
    public static function getSetting(
        string $key,
        ?Carbon $date = null,
        $default = null
    ) {
        $date = $date ?? now();

        return static::query()
            ->where('setting_key', $key)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhereDate(
                        'effective_to',
                        '>=',
                        $date->toDateString()
                    );
            })
            ->orderByDesc('effective_from')
            ->value('value') ?? $default;
    }

    /**
     * Get a decimal statutory setting.
     */
    public static function getDecimal(
        string $key,
        ?Carbon $date = null,
        float $default = 0.00
    ): float {
        $value = static::getSetting($key, $date);

        return $value !== null
            ? (float) $value
            : $default;
    }

    /**
     * Get an integer statutory setting.
     */
    public static function getInteger(
        string $key,
        ?Carbon $date = null,
        int $default = 0
    ): int {
        $value = static::getSetting($key, $date);

        return $value !== null
            ? (int) $value
            : $default;
    }

    /**
     * Get a boolean statutory setting.
     */
    public static function getBoolean(
        string $key,
        ?Carbon $date = null,
        bool $default = false
    ): bool {
        $value = static::getSetting($key, $date);

        if ($value === null) {
            return $default;
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN
        );
    }
}