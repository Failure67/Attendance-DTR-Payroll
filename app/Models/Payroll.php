<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'wage_type',
        'min_wage',
        'hours_worked',
        'days_worked',
        'regular_hours',
        'overtime_hours',
        'absent_days',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'status',
        'snapshot',
        'period_start',
        'period_end',
        'user_id',
        'hr_approved_by',
        'hr_approved_at',
        'admin_approved_by',
        'admin_approved_at',
    ];

    protected $casts = [
        'released_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'min_wage' => 'decimal:2',
        'hours_worked' => 'decimal:2',
        'days_worked' => 'decimal:2',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'absent_days' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'hr_approved_at' => 'datetime',
        'admin_approved_at' => 'datetime',
        'snapshot' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deductions()
    {
        return $this->hasMany(PayrollDeduction::class, 'payroll_id');
    }

    public function cashAdvances()
    {
        return $this->hasMany(CashAdvance::class, 'payroll_id');
    }
}
