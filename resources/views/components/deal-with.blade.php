@props(['visit'])
@php($others = $visit->deal ? $visit->deal->visits->reject(fn ($o) => $o->id === $visit->id) : collect())
@if ($others->isNotEmpty())
    <span {{ $attributes->class(['deal-tag']) }}>{{ __('مرتبط بـ') }}
        @foreach ($others as $o)<bdi>{{ $o->ticket?->ticket_no ?? '' }} {{ $o->customer->shortName() }}</bdi>@if (! $loop->last)، @endif @endforeach
    </span>
@endif
