<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RemittanceLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'user_id',
        'employee_name',
        'membership_number',
        'employee_amount',
        'employer_amount',
        'total_amount',
        'missing_membership',
    ];

    protected $casts = [
        'employee_amount' => 'decimal:2',
        'employer_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'missing_membership' => 'boolean',
    ];

    public function batch()
    {
        return $this->belongsTo(RemittanceBatch::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
