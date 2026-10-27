@props(['type', 'label' => null])
<span {{ $attributes->class(['type-tag', 'type-'.$type->value]) }}>{{ $label ?? $type->label() }}</span>
