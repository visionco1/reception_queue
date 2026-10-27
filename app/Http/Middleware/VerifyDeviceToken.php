<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * روابط الأجهزة (آيباد الاستقبال وشاشة العرض) تعمل بدون تسجيل دخول،
 * لكنها محمية برمز سري في الرابط يمكن تغييره من لوحة الإدارة.
 */
class VerifyDeviceToken
{
    public function handle(Request $request, Closure $next, string $device): Response
    {
        $expected = (string) setting($device.'_token');
        $given = (string) $request->route('token');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            abort(404);
        }

        return $next($request);
    }
}
