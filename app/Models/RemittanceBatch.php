<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RemittanceBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency',
        'period_month',
        'status',
        'employee_total',
        'employer_total',
        'grand_total',
        'payment_reference',
        'paid_at',
        'submitted_at',
        'proof_path',
        'created_by',
    ];

    protected $casts = [
        'period_month' => 'date',
        'paid_at' => 'datetime',
        'submitted_at' => 'datetime',
        'employee_total' => 'decimal:2',
        'employer_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(RemittanceLineItem::class, 'batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
