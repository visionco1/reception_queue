@extends('layouts.admin')
@section('title', __('العملاء'))

@section('admin')
<x-page-header :title="__('قاعدة العملاء')" :subtitle="__(':n عميل مسجل', ['n' => number_format($total)])">
    <a class="btn btn-quiet" href="{{ route('admin.export.customers', ['format' => 'xlsx']) }}">{{ __('تصدير Excel') }}</a>
    <a class="btn btn-quiet" href="{{ route('admin.export.customers', ['format' => 'csv']) }}">{{ __('تصدير CSV') }}</a>
</x-page-header>

<form class="panel p-3 mb-4 flex flex-wrap items-center gap-3">
    <label for="q" class="sr-only">{{ __('بحث') }}</label>
    <input id="q" name="q" value="{{ $q }}" class="field !w-72" placeholder="{{ __('الاسم أو رقم الجوال') }}">
    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="repeat" value="1" @checked($repeat) class="size-4 accent-deed"> {{ __('المتكررون فقط') }}
    </label>
    <button class="btn btn-primary !py-1.5">{{ __('بحث') }}</button>
</form>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead><tr><th>{{ __('الاسم') }}</th><th>{{ __('الجوال') }}</th><th>{{ __('الزيارات') }}</th><th>{{ __('آخر زيارة') }}</th><th>{{ __('تاريخ التسجيل') }}</th></tr></thead>
        <tbody>
            @forelse ($customers as $c)
                <tr>
                    <td><a href="{{ route('staff.customers.show', $c) }}" class="font-semibold hover:text-deed">{{ $c->name }}</a></td>
                    <td class="ltr-nums text-start">{{ $c->displayMobile() }}</td>
                    <td class="ltr-nums text-start">{{ $c->visits_count }}</td>
                    <td class="text-ink-soft">{{ $c->last_visit_at?->translatedFormat('j M Y') ?? '—' }}</td>
                    <td class="text-ink-soft">{{ $c->created_at->translatedFormat('j M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-ink-faint py-10">{{ __('لا يوجد عملاء مطابقون.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $customers->links() }}
@endsection
