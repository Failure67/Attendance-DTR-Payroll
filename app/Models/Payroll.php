<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'wage_type',
        'min_wage',
        'hours_worked',
        'days_worked',
        'gross_pay',
        'deductions',
        'net_pay',
        'status',
        'user_id',
    ];

    protected $casts = [
        'released_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
