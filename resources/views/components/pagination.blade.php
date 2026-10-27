@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-4 mt-4 text-sm" aria-label="{{ __('الصفحات') }}">
        <div class="text-ink-faint">
            {{ __('عرض :from–:to من :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </div>
        <div class="flex items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-quiet btn-sm opacity-40">{{ __('السابق') }}</span>
            @else
                <a class="btn btn-quiet btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('السابق') }}</a>
            @endif
            <span class="text-ink-soft ltr-nums">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            @if ($paginator->hasMorePages())
                <a class="btn btn-quiet btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('التالي') }}</a>
            @else
                <span class="btn btn-quiet btn-sm opacity-40">{{ __('التالي') }}</span>
            @endif
        </div>
    </nav>
@endif
