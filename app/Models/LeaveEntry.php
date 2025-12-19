<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'date_start',
        'date_end',
        'duration_days',
        'type',
        'is_paid',
        'status',
        'requested_by_id',
        'approved_by_id',
        'approved_at',
        'supervisor_approved_at',
        'manager_approved_at',
        'hr_approved_at',
        'reason',
        'meta',
        'payroll_id',
        'paid_amount',
    ];

    protected $casts = [
        'date_start' => 'date',
        'date_end' => 'date',
        'duration_days' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'supervisor_approved_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
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
