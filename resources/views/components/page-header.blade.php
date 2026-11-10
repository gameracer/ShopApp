@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">{{ $title }}</h1>
        @if ($subtitle)<p class="text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="no-print flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
