<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** إضافة وتعديل وتعطيل الموظفين وتحديد صلاحياتهم ومكاتبهم */
class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'users' => User::query()->with('desk', 'currentDesk')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true, 'role' => Role::Employee]), 'desks' => $this->desks()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);
        AuditLog::record('user.created', $user, __('إضافة المستخدم :name (:role)', ['name' => $user->name, 'role' => $user->role->label()]));

        return redirect()->route('admin.users.index')->with('status', __('أُضيف :name', ['name' => $user->name]));
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'desks' => $this->desks()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && (! $data['is_active'] || $data['role'] !== Role::Admin->value)) {
            return back()->with('error', __('لا يمكنك تعطيل حسابك أو إزالة صلاحية المدير عن نفسك.'));
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $before = $user->only(['name', 'username', 'role', 'desk_id', 'is_active']);
        $user->update($data);
        $changes = collect($user->getChanges())->except(['updated_at', 'password', 'remember_token'])
            ->map(fn ($v, $k) => [$before[$k] instanceof \BackedEnum ? $before[$k]->value : $before[$k], $v])->all();
        if (isset($data['password'])) {
            $changes['password'] = __('تم تغييرها');
        }
        AuditLog::record('user.updated', $user, __('تعديل المستخدم :name', ['name' => $user->name]), $changes);

        return redirect()->route('admin.users.index')->with('status', __('حُفظت بيانات :name', ['name' => $user->name]));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')->ignore($user)],
            'email' => ['nullable', 'email', 'max:150'],
            'role' => ['required', Rule::enum(Role::class)],
            'desk_id' => ['nullable', 'exists:desks,id'],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(8)],
        ], [
            'username.regex' => __('اسم المستخدم بالأحرف الإنجليزية والأرقام فقط'),
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['username'] = strtolower($data['username']);

        return $data;
    }

    private function desks()
    {
        return Desk::query()->ordered()->get();
    }
}
