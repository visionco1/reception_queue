<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->query('user');
        $action = $request->query('action');

        $logs = AuditLog::query()->with('user')
            ->when($user, fn ($q) => $q->where('user_id', (int) $user))
            ->when($action, fn ($q) => $q->where('action', 'like', $action.'%'))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'user' => $user,
            'action' => $action,
            'actions' => [
                'visit' => __('الطابور والزيارات'),
                'customer' => __('بيانات العملاء'),
                'user' => __('المستخدمون'),
                'settings' => __('الإعدادات'),
                'messages' => __('رسائل واتساب'),
                'export' => __('التصدير'),
                'auth' => __('الدخول والخروج'),
            ],
        ]);
    }
}
