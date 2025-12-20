<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveCreditTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'direction',
        'amount',
        'remaining_amount',
        'occurred_at',
        'effective_date',
        'type',
        'actor_id',
        'reference_type',
        'reference_id',
        'description',
        'expires_at',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'remaining_amount' => 'decimal:3',
        'occurred_at' => 'datetime',
        'effective_date' => 'date',
        'expires_at' => 'date',
        'meta' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(LeaveCreditAccount::class, 'account_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function debitAllocations()
    {
        return $this->hasMany(LeaveCreditAllocation::class, 'debit_transaction_id');
    }

    public function creditAllocations()
    {
        return $this->hasMany(LeaveCreditAllocation::class, 'credit_transaction_id');
    }
}
