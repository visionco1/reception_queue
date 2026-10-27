@extends('layouts.admin')
@section('title', $user->exists ? __('تعديل موظف') : __('إضافة موظف'))

@section('admin')
<x-page-header :title="$user->exists ? __('تعديل :name', ['name' => $user->name]) : __('إضافة موظف')" />

<form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="panel p-6 max-w-2xl grid sm:grid-cols-2 gap-5">
    @csrf
    @if ($user->exists) @method('PUT') @endif

    <div class="sm:col-span-2">
        <label for="name" class="field-label">{{ __('الاسم') }}</label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" class="field" required>
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="username" class="field-label">{{ __('اسم المستخدم للدخول') }}</label>
        <input id="username" name="username" value="{{ old('username', $user->username) }}" class="field" dir="ltr" autocapitalize="none" required>
        @error('username')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="password" class="field-label">{{ __('كلمة المرور') }}</label>
        <input id="password" type="password" name="password" class="field" dir="ltr" autocomplete="new-password" @required(! $user->exists)>
        <p class="field-hint">{{ $user->exists ? __('اتركها فارغة للإبقاء على الحالية.') : __('8 أحرف على الأقل.') }}</p>
        @error('password')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="role" class="field-label">{{ __('الصلاحية') }}</label>
        <select id="role" name="role" class="field">
            @foreach (\App\Enums\Role::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="desk_id" class="field-label">{{ __('المكتب الافتراضي') }}</label>
        <select id="desk_id" name="desk_id" class="field">
            <option value="">{{ __('يختاره عند بداية الدوام') }}</option>
            @foreach ($desks as $d)
                <option value="{{ $d->id }}" @selected((string) old('desk_id', $user->desk_id) === (string) $d->id)>{{ __('مكتب :n', ['n' => $d->number]) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="email" class="field-label">{{ __('البريد الإلكتروني (اختياري)') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="field" dir="ltr">
        @error('email')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <label class="flex items-center gap-3 self-end pb-2">
        <span class="switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))><span></span></span>
        {{ __('الحساب فعّال') }}
    </label>

    <div class="sm:col-span-2 flex gap-2 pt-2">
        <button class="btn btn-primary">{{ $user->exists ? __('حفظ التعديلات') : __('إضافة الموظف') }}</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-quiet">{{ __('رجوع') }}</a>
    </div>
</form>
@endsection
