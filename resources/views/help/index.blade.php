@extends('layouts.app')
@section('title', __('Help'))

@section('content')
<x-page-header :title="$title" :subtitle="__('How to use ShopApp, step by step.')">
    <x-slot:actions>
        @foreach (\App\Support\Shop::LOCALES as $code => $name)
            @if ($code !== app()->getLocale())
                <a href="{{ route('locale.switch', $code) }}" class="btn-secondary"><x-icon name="globe" class="h-4 w-4" /> {{ $name }}</a>
            @endif
        @endforeach
    </x-slot:actions>
</x-page-header>

<div class="grid gap-6 lg:grid-cols-4">
    <aside class="lg:col-span-1">
        <nav class="card p-2 text-sm lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto" aria-label="{{ __('Contents') }}">
            <p class="px-2 py-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ __('Contents') }}</p>
            @foreach ($toc as $i => $item)
                <a href="#{{ $item['id'] }}" class="flex gap-2 rounded-md px-2 py-1.5 text-slate-700 hover:bg-slate-100 hover:text-emerald-700">
                    <span class="w-5 shrink-0 text-right text-slate-400 tabular-nums">{{ $i + 1 }}.</span><span>{{ $item['title'] }}</span>
                </a>
            @endforeach
        </nav>
    </aside>

    <article class="manual card min-w-0 p-5 sm:p-8 lg:col-span-3">
        {!! $html !!}
    </article>
</div>
@endsection
