@props(['tag', 'active' => false])
@php
$colors = [
    'emerald' => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
    'sky' => 'bg-sky-100 text-sky-800 ring-sky-300',
    'amber' => 'bg-amber-100 text-amber-800 ring-amber-300',
    'rose' => 'bg-rose-100 text-rose-800 ring-rose-300',
    'violet' => 'bg-violet-100 text-violet-800 ring-violet-300',
    'slate' => 'bg-slate-200 text-slate-800 ring-slate-300',
    'orange' => 'bg-orange-100 text-orange-800 ring-orange-300',
    'teal' => 'bg-teal-100 text-teal-800 ring-teal-300',
];
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', $colors[$tag->color] ?? $colors['slate'], 'ring-2' => $active]) }}>#{{ $tag->name }}</span>
