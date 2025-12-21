<?php

namespace App\Providers;

use App\Models\Attendance;
use App\Models\CashAdvanceRequest;
use App\Models\LeaveEntry;
use App\Models\Payroll;
use App\Policies\AttendancePolicy;
use App\Policies\CashAdvancePolicy;
use App\Policies\LeaveEntryPolicy;
use App\Policies\PayrollPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Attendance::class => AttendancePolicy::class,
        CashAdvanceRequest::class => CashAdvancePolicy::class,
        LeaveEntry::class => LeaveEntryPolicy::class,
        Payroll::class => PayrollPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(12);

            return app()->isProduction()
                ? $rule->mixedCase()->letters()->numbers()->symbols()->uncompromised()
                : $rule;
        });
    }
}
