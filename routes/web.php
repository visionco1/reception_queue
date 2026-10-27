<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Display\DisplayController;
use App\Http\Controllers\Kiosk\KioskController;
use App\Http\Controllers\Staff;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'staff.dashboard' : 'login'));

// تسجيل دخول الموظفين والإدارة
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
});
Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// آيباد الاستقبال — بدون تسجيل دخول، محمي برمز سري في الرابط
Route::prefix('kiosk/{token}')->middleware('device:kiosk')->name('kiosk.')->group(function () {
    Route::get('/', [KioskController::class, 'show'])->name('show');
    Route::post('register', [KioskController::class, 'register'])->middleware('throttle:kiosk')->name('register');
    Route::get('manifest.webmanifest', [KioskController::class, 'manifest'])->name('manifest');
});

// شاشة الانتظار الرئيسية
Route::prefix('display/{token}')->middleware('device:display')->name('display.')->group(function () {
    Route::get('/', [DisplayController::class, 'show'])->name('show');
    Route::get('feed', [DisplayController::class, 'feed'])->name('feed');
});

// Webhook حالات تسليم رسائل واتساب
Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('webhooks.whatsapp');

// لوحة الموظف
Route::middleware(['auth', 'active'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [Staff\DashboardController::class, 'index'])->name('dashboard');
    Route::get('state', [Staff\DashboardController::class, 'state'])->name('state');
    Route::post('desk', [Staff\DashboardController::class, 'setDesk'])->name('desk');

    Route::controller(Staff\QueueActionController::class)->prefix('visits/{visit}')->name('visits.')->group(function () {
        Route::post('take', 'take')->name('take');
        Route::post('recall', 'recall')->name('recall');
        Route::post('start', 'start')->name('start');
        Route::post('complete', 'complete')->name('complete');
        Route::post('no-show', 'noShow')->name('no-show');
        Route::post('release', 'release')->name('release');
        Route::post('cancel', 'cancel')->name('cancel');
        Route::get('candidates', 'candidates')->name('candidates');
        Route::post('link', 'link')->name('link');
        Route::post('unlink', 'unlink')->name('unlink');
        Route::post('agent', 'agent')->name('agent');
    });

    Route::get('lookup', [Staff\CustomerController::class, 'lookup'])->name('lookup');
    Route::post('remote', [Staff\CustomerController::class, 'storeRemote'])->name('remote.store');
    Route::get('customers', [Staff\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [Staff\CustomerController::class, 'show'])->name('customers.show');
    Route::put('customers/{customer}', [Staff\CustomerController::class, 'update'])->name('customers.update');
    Route::get('today', [Staff\DashboardController::class, 'today'])->name('today');
});

// لوحة الإدارة — المشرف والمدير
Route::middleware(['auth', 'active', 'role:admin,supervisor'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\OverviewController::class, 'index'])->name('overview');
    Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports');

    Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers');
    Route::get('visits', [Admin\VisitController::class, 'index'])->name('visits');
    Route::get('visits/{visit}', [Admin\VisitController::class, 'show'])->name('visits.show');

    Route::get('export/visits', [Admin\ExportController::class, 'visits'])->name('export.visits');
    Route::get('export/customers', [Admin\ExportController::class, 'customers'])->name('export.customers');

    Route::get('whatsapp', [Admin\WhatsAppLogController::class, 'index'])->name('whatsapp');
    Route::post('whatsapp/{log}/retry', [Admin\WhatsAppLogController::class, 'retry'])->name('whatsapp.retry');

    // مدير النظام فقط: المستخدمون والإعدادات
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', Admin\UserController::class)->except(['show', 'destroy']);

        Route::get('desks', [Admin\DeskController::class, 'index'])->name('desks');
        Route::post('desks', [Admin\DeskController::class, 'store'])->name('desks.store');
        Route::put('desks/{desk}', [Admin\DeskController::class, 'update'])->name('desks.update');

        Route::get('banks', [Admin\BankController::class, 'index'])->name('banks');
        Route::post('banks', [Admin\BankController::class, 'store'])->name('banks.store');
        Route::put('banks/{bank}', [Admin\BankController::class, 'update'])->name('banks.update');

        Route::get('agents', [Admin\AgentController::class, 'index'])->name('agents');
        Route::post('agents', [Admin\AgentController::class, 'store'])->name('agents.store');
        Route::put('agents/{agent}', [Admin\AgentController::class, 'update'])->name('agents.update');

        Route::get('client-types', [Admin\ClientTypeController::class, 'index'])->name('client-types');
        Route::post('client-types', [Admin\ClientTypeController::class, 'store'])->name('client-types.store');
        Route::put('client-types/{clientType}', [Admin\ClientTypeController::class, 'update'])->name('client-types.update');

        Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/token/{device}', [Admin\SettingsController::class, 'regenerateToken'])
            ->whereIn('device', ['kiosk', 'display'])->name('settings.token');

        Route::get('messages', [Admin\MessageController::class, 'edit'])->name('messages');
        Route::put('messages', [Admin\MessageController::class, 'update'])->name('messages.update');
        Route::post('messages/test', [Admin\MessageController::class, 'test'])->name('messages.test');

        Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit');
    });
});
