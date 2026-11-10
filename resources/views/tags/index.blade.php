@extends('layouts.app')
@section('title', __('Tags'))

@section('content')
<x-page-header :title="__('Tags')" :subtitle="__('Use tags to group and quickly find products (e.g. drinks, food, cosmetics).')" />

<div class="grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('tags.store') }}" class="card space-y-3 p-4">
        @csrf
        <h2 class="card-title">{{ __('New tag') }}</h2>
        <input name="name" class="input" placeholder="{{ __('Tag name') }}" required>
        @include('tags._colors', ['current' => 'emerald'])
        <button class="btn-primary w-full">{{ __('Save') }}</button>
    </form>

    <div class="card lg:col-span-2">
        <ul class="divide-y divide-slate-100">
            @forelse ($tags as $tag)
                <li class="flex flex-wrap items-center gap-3 px-4 py-3" x-data="{ edit: false }">
                    <div x-show="!edit" class="flex flex-1 items-center gap-3">
                        <x-tag :tag="$tag" />
                        <a href="{{ route('products.index', ['tags' => [$tag->id]]) }}" class="text-xs text-slate-500 hover:underline">{{ trans_choice(':count product|:count products', $tag->products_count) }}</a>
                    </div>
                    <form x-cloak x-show="edit" method="POST" action="{{ route('tags.update', $tag) }}" class="flex flex-1 flex-wrap items-center gap-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $tag->name }}" class="input w-40" required>
                        @include('tags._colors', ['current' => $tag->color])
                        <button class="btn-primary btn-sm">{{ __('Save') }}</button>
                    </form>
                    <button class="btn-secondary btn-sm" @click="edit = !edit" x-text="edit ? '{{ __('Cancel') }}' : '{{ __('Edit') }}'"></button>
                    <form method="POST" action="{{ route('tags.destroy', $tag) }}" onsubmit="return confirm('{{ __('Delete this tag?') }}')">
                        @csrf @method('DELETE')
                        <button class="btn-secondary btn-sm text-rose-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                    </form>
                </li>
            @empty
                <li><x-empty>{{ __('No tags yet.') }}</x-empty></li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
