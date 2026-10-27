@extends('layouts.admin')
@section('title', __('سجل رسائل واتساب'))

@section('admin')
<x-page-header :title="__('سجل رسائل واتساب')" :subtitle="__('حالة كل رسالة ووقت إرسالها. الرسائل الفاشلة تُعاد تلقائيًا ثلاث مرات قبل تسجيل الفشل.')" />

<div class="flex flex-wrap gap-2 mb-4 text-sm">
    <a href="{{ route('admin.whatsapp') }}" @class(['btn btn-sm', 'btn-primary' => ! $status, 'btn-quiet' => $status])>{{ __('الكل') }}</a>
    @foreach (\App\Models\WhatsappLog::STATUSES as $s)
        <a href="{{ route('admin.whatsapp', ['status' => $s, 'key' => $key]) }}" @class(['btn btn-sm', 'btn-primary' => $status === $s, 'btn-quiet' => $status !== $s])>
            {{ \App\Models\WhatsappLog::statusLabel($s) }}
            @if ($counts[$s] ?? 0)<span class="ltr-nums opacity-70">{{ $counts[$s] }}</span>@endif
        </a>
    @endforeach
    <span class="text-ink-faint self-center ms-2">{{ __('الأعداد لآخر 7 أيام') }}</span>
</div>

<div class="panel overflow-x-auto">
    <table class="data-table">
        <thead>
            <tr><th>{{ __('الوقت') }}</th><th>{{ __('العميل') }}</th><th>{{ __('الرسالة') }}</th><th>{{ __('النص') }}</th><th>{{ __('الحالة') }}</th><th class="w-1"></th></tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap text-sm">{{ $log->created_at->translatedFormat('j M') }} <span class="ltr-nums text-ink-faint">{{ $log->created_at->format('H:i') }}</span></td>
                    <td class="whitespace-nowrap">
                        <div class="font-semibold">{{ $log->customer?->name ?? '—' }}</div>
                        <div class="text-xs text-ink-faint ltr-nums text-start">{{ \App\Support\Phone::format($log->to_phone) }}</div>
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        <span class="inline-flex items-baseline gap-1.5">{{ $log->message_key->label() }}
                        @if ($log->visit?->ticket)<span class="ticket-code">{{ $log->visit->ticket->ticket_no }}</span>@endif</span>
                        @if ($log->template_name)<div class="text-xs text-ink-faint" dir="ltr">{{ $log->template_name }}</div>@endif
                    </td>
                    <td class="text-sm text-ink-soft max-w-sm">
                        {{ $log->body }}
                        @if ($log->error)<div class="text-danger text-xs mt-1" dir="auto">{{ $log->error }}</div>@endif
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        <span @class(['font-semibold', 'text-danger' => $log->status === 'failed', 'text-ok' => in_array($log->status, ['delivered', 'read'])])>{{ \App\Models\WhatsappLog::statusLabel($log->status) }}</span>
                        @if ($log->attempts > 1)<div class="text-xs text-ink-faint">{{ __(':n محاولات', ['n' => $log->attempts]) }}</div>@endif
                    </td>
                    <td>
                        @if ($log->status === 'failed')
                            <form method="POST" action="{{ route('admin.whatsapp.retry', $log) }}">@csrf<button class="btn btn-quiet btn-sm">{{ __('إعادة الإرسال') }}</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-ink-faint py-10">{{ __('لا توجد رسائل.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $logs->links() }}
@endsection
