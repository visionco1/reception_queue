<?php

namespace App\Providers;

use App\Support\Settings;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped: نسخة جديدة لكل طلب أو مهمة طابور حتى لا تبقى إعدادات قديمة في العامل
        $this->app->scoped(Settings::class);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        CarbonImmutable::setLocale(config('app.locale'));
        Paginator::defaultView('components.pagination');

        // تسجيل العملاء من الآيباد: حد معقول يمنع الإغراق دون التأثير على الاستخدام الطبيعي
        // بيانات نافذة «إضافة عميل عن بعد» أينما ظهرت
        View::composer('staff.partials.remote-modal', function ($view) {
            $banks = \App\Models\Bank::query()->active()->ordered()
                ->with(['agents' => fn ($q) => $q->active()->ordered()->select('id', 'bank_id', 'name')])
                ->get(['id', 'name']);
            $view->with([
                'remoteBanks' => $banks,
                'remoteAgents' => $banks->mapWithKeys(fn ($b) => [(string) $b->id => $b->agents->map(fn ($a) => ['id' => (string) $a->id, 'name' => $a->name])->values()]),
                'remoteSources' => \App\Enums\ClientSource::options(),
                'remoteTypes' => \App\Enums\CustomerType::options(),
                'clientTypes' => \App\Models\ClientType::query()->active()->ordered()->get(['id', 'name']),
            ]);
        });

        RateLimiter::for('kiosk', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(8)->by(strtolower((string) $request->input('username')).'|'.$request->ip()));
    }
}
