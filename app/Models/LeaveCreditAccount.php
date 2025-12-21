<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveCreditAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'leave_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions()
    {
        return $this->hasMany(LeaveCreditTransaction::class, 'account_id');
    }
}
