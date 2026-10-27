@extends('layouts.admin')
@section('title', __('الموظفون'))

@section('admin')
<x-page-header :title="__('الموظفون والصلاحيات')">
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">{{ __('إضافة موظف') }}</a>
</x-page-header>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead><tr><th>{{ __('الاسم') }}</th><th>{{ __('اسم المستخدم') }}</th><th>{{ __('الصلاحية') }}</th><th>{{ __('المكتب') }}</th><th>{{ __('آخر دخول') }}</th><th>{{ __('الحالة') }}</th><th class="w-1"></th></tr></thead>
        <tbody>
            @foreach ($users as $u)
                <tr @class(['opacity-55' => ! $u->is_active])>
                    <td class="font-semibold">{{ $u->name }}</td>
                    <td dir="ltr" class="text-start">{{ $u->username }}</td>
                    <td>{{ $u->role->label() }}</td>
                    <td>
                        {{ $u->desk ? __('مكتب :n', ['n' => $u->desk->number]) : '—' }}
                        @if ($u->currentDesk && $u->currentDesk->isNot($u->desk))
                            <span class="text-xs text-ink-faint">({{ __('يعمل الآن على :n', ['n' => $u->currentDesk->number]) }})</span>
                        @endif
                    </td>
                    <td class="text-ink-soft text-sm">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                    <td>{{ $u->is_active ? __('فعّال') : __('معطّل') }}</td>
                    <td><a href="{{ route('admin.users.edit', $u) }}" class="btn btn-quiet btn-sm">{{ __('تعديل') }}</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="text-sm text-ink-faint mt-4 max-w-2xl">
    {{ __('الموظف: يرى الطابور ويأخذ العملاء ويضيف العملاء عن بعد ويبحث في العملاء. المشرف: يرى أيضًا التقارير وجميع الزيارات والتصدير وسجل الرسائل. مدير النظام: كل ما سبق مع إدارة الموظفين والإعدادات.') }}
</p>
@endsection
