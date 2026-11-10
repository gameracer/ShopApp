@props(['label', 'value', 'hint' => null, 'tone' => 'slate', 'icon' => null])
@php
$tones = [
    'slate' => 'bg-slate-100 text-slate-600',
    'emerald' => 'bg-emerald-100 text-emerald-700',
    'sky' => 'bg-sky-100 text-sky-700',
    'amber' => 'bg-amber-100 text-amber-700',
    'rose' => 'bg-rose-100 text-rose-700',
    'violet' => 'bg-violet-100 text-violet-700',
];
@endphp
<div class="card flex items-start gap-3 p-4">
    @if ($icon)
        <div class="rounded-lg p-2 {{ $tones[$tone] ?? $tones['slate'] }}"><x-icon :name="$icon" /></div>
    @endif
    <div class="min-w-0">
        <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ $label }}</p>
        <p class="mt-1 truncate text-xl font-semibold text-slate-900">{{ $value }}</p>
        @if ($hint)<p class="mt-0.5 text-xs text-slate-500">{{ $hint }}</p>@endif
    </div>
</div>
