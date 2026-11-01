<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Desk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.login', [
            'desks' => Desk::query()->active()->ordered()->get(['id', 'number']),
            'rememberedDesk' => $request->cookie('qms_desk'),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'desk_id' => ['nullable', 'integer', 'exists:desks,id'],
        ], [
            'username.required' => __('اكتب اسم المستخدم'),
            'password.required' => __('اكتب كلمة المرور'),
        ]);
        $deskId = $credentials['desk_id'] ?? null;
        unset($credentials['desk_id']);

        $credentials['is_active'] = true;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => __('اسم المستخدم أو كلمة المرور غير صحيحة، أو أن الحساب معطل.'),
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(array_filter([
            'last_login_at' => now(),
            'current_desk_id' => $deskId,
        ]))->saveQuietly();
        AuditLog::record('auth.login', $user, __('تسجيل دخول :name', ['name' => $user->name]));

        // المكتب يُحفظ على هذا الجهاز مع «تذكرني»
        $cookie = $request->boolean('remember') && $deskId
            ? cookie()->forever('qms_desk', (string) $deskId)
            : cookie()->forget('qms_desk');

        return redirect()->intended(route('staff.dashboard'))->withCookie($cookie);
    }

    public function logout(Request $request)
    {
        AuditLog::record('auth.logout', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
