<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'time_in',
        'time_out',
        'date',
        'total_hours',
        'overtime_hours',
        'status',
        'overtime_approved',
        'leave_approved',
    ];

    protected $casts = [
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'date' => 'date',
        'total_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_approved' => 'boolean',
        'leave_approved' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
