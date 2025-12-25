<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'full_name',
        'email',
        'password',
        'profile_picture',
        'role',
        'employment_type',
        'employment_start_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'employment_start_date' => 'date',
    ];

    public const EMPLOYMENT_TYPE_REGULAR = 'regular';
    public const EMPLOYMENT_TYPE_PART_TIME = 'part_time';

    public function userCredential()
    {
        return $this->hasOne(UserCredential::class);
    }

    public function payroll()
    {
        return $this->hasMany(Payroll::class);
    }

    public function cashAdvances()
    {
        return $this->hasMany(CashAdvance::class);
    }

    public function isRegular(): bool
    {
        $type = $this->employment_type ?? self::EMPLOYMENT_TYPE_REGULAR;

        return $type === self::EMPLOYMENT_TYPE_REGULAR;
    }

    public function isPartTime(): bool
    {
        $type = $this->employment_type ?? self::EMPLOYMENT_TYPE_REGULAR;

        return $type === self::EMPLOYMENT_TYPE_PART_TIME;
    }
}
