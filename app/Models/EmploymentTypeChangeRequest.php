<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentTypeChangeRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_OVERRIDDEN = 'overridden';

    protected $fillable = [
        'user_id',
        'requested_by_id',
        'manager_id',
        'admin_id',
        'from_type',
        'to_type',
        'status',
        'reason',
        'manager_reason',
        'admin_reason',
        'before_snapshot',
        'after_snapshot',
        'approved_at',
        'rejected_at',
        'overridden_at',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'overridden_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
