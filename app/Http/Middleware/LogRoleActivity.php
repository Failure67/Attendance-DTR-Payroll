<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogRoleActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only log write actions
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        // Resolve current user across guards (mirror CheckUserRole logic)
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
            return $response;
        }

        $roleKey = strtolower(trim((string) $user->role));

        // Only record logs for HR and Supervisor accounts
        if (!in_array($roleKey, ['hr', 'supervisor'], true)) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route ? $route->getName() : null;

        $action = $request->method() . ' ' . ($routeName ?: $request->path());

        // Avoid logging very large payloads: capture only a shallow snapshot without sensitive fields
        $input = $request->except([
            'password',
            'password_confirmation',
            '_token',
            '_method',
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'role' => $user->role,
            'action' => $action,
            'description' => json_encode([
                'route' => $routeName,
                'path' => $request->path(),
                'method' => $request->method(),
                'input' => $input,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => (string) substr($request->userAgent() ?? '', 0, 500),
        ]);

        return $response;
    }
}
