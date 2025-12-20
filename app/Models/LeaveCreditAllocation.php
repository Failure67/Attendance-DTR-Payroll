<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveCreditAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'debit_transaction_id',
        'credit_transaction_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
    ];

    public function debitTransaction()
    {
        return $this->belongsTo(LeaveCreditTransaction::class, 'debit_transaction_id');
    }

    public function creditTransaction()
    {
        return $this->belongsTo(LeaveCreditTransaction::class, 'credit_transaction_id');
    }
}
