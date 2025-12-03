<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if ($request->is('worker*') && Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
        } elseif (Auth::guard('superadmin')->check()) {
            $user = Auth::guard('superadmin')->user();
        } elseif (Auth::guard('admin')->check()) {
            $user = Auth::guard('admin')->user();
        } elseif (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
        } else {
            $user = null;
        }

        if (!$user) {
            return redirect()->route('auth.login.show');
        }

        $userRole = strtolower(trim((string) $user->role));

        $normalizedRoles = array_map(function ($role) {
            return strtolower(trim((string) $role));
        }, $roles);

        if (in_array($userRole, $normalizedRoles, true)) {
            return $next($request);
        }

        if (in_array($userRole, ['admin', 'superadmin', 'hr', 'accounting', 'project manager'], true)) {
            return redirect()->route('admin.dashboard');
        }

        if ($userRole === 'supervisor') {
            return redirect()->route('attendance');
        }

        return redirect()->route('worker.dashboard');
    }
}
