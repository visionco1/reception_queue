<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** إنهاء جلسة الموظف فور تعطيل حسابه من لوحة الإدارة */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('login')->withErrors(['username' => __('تم تعطيل هذا الحساب. تواصل مع الإدارة.')]);
        }

        return $next($request);
    }
}
