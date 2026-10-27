@props(['title', 'subtitle' => null])
<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
        <h1 class="font-display text-2xl font-bold">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-ink-soft mt-1">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
