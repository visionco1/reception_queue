<?php

use App\Exceptions\QueueException;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\VerifyDeviceToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'active' => EnsureActiveUser::class,
            'device' => VerifyDeviceToken::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('staff.dashboard'));
        // Webhook حالات تسليم واتساب يأتي من Meta بدون CSRF (يُتحقق منه بالتوقيع)
        // آيباد الاستقبال يبقى مفتوحًا طوال اليوم: نحميه بالرمز السري في الرابط بدل رمز الجلسة الذي ينتهي
        $middleware->validateCsrfTokens(except: ['webhooks/whatsapp', 'kiosk/*/register']);
        // إذا كان الخادم خلف موازن أحمال أو Cloudflare فعّل السطر التالي بعناوين الوكلاء:
        // $middleware->trustProxies(at: ['10.0.0.0/8']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // أخطاء الطابور المتوقعة (عميل أُخذ للتو...) ليست أعطالًا ولا تُسجَّل في السجل
        $exceptions->dontReport(QueueException::class);

        $exceptions->render(function (QueueException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->status);
            }

            return back()->with('error', $e->getMessage());
        });
    })->create();
