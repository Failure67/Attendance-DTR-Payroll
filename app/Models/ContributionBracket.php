<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContributionBracket extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'range_from',
        'range_to',
        'employee_rate',
        'employee_amount',
    ];

    protected $casts = [
        'range_from' => 'decimal:2',
        'range_to' => 'decimal:2',
        'employee_rate' => 'decimal:6',
        'employee_amount' => 'decimal:2',
    ];

    public static function calculateAmount(string $type, float $base): float
    {
        $bracket = self::where('type', $type)
            ->where('range_from', '<=', $base)
            ->where(function ($q) use ($base) {
                $q->whereNull('range_to')
                    ->orWhere('range_to', '>=', $base);
            })
            ->orderBy('range_from')
            ->first();

        if (!$bracket) {
            return 0.0;
        }

        if (!is_null($bracket->employee_amount)) {
            return (float) $bracket->employee_amount;
        }

        if (!is_null($bracket->employee_rate)) {
            return round($base * (float) $bracket->employee_rate, 2);
        }

        return 0.0;
    }
}
