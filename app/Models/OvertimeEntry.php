<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'date',
        'hours',
        'premium_multiplier',
        'status',
        'requested_by_id',
        'approved_by_id',
        'approved_at',
        'reason',
        'meta',
        'payroll_id',
        'premium_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
        'premium_multiplier' => 'decimal:2',
        'premium_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
