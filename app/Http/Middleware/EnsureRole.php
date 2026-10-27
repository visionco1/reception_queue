<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** يسمح بالدخول للأدوار المحددة فقط: ->middleware('role:admin,supervisor') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403, __('ليست لديك صلاحية للوصول إلى هذه الصفحة.'));
        }

        return $next($request);
    }
}
