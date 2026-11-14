@props(['tags', 'showStock' => true])
{{-- Shared search panel used inside posApp / stockApp Alpine components. Also receives barcode scans. --}}
<div class="card" @barcode.window="onScan($event)">
    <div class="space-y-3 border-b border-slate-100 p-4">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-slate-400" />
            <input x-ref="search" x-model="query" @input="onQuery" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="pickHighlighted()"
                   class="input pl-9" placeholder="{{ __('Type product name, scan barcode or #tag…') }}" autofocus autocomplete="off">
        </div>
        <div x-cloak x-show="scanNotice" x-transition.opacity
             :class="scanNotice?.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-700'"
             class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm" role="status">
            <span><span x-text="scanNotice?.ok ? '✓' : '✕'"></span> <span x-text="scanNotice?.text"></span></span>
            @if (auth()->user()?->isOwner())
                <a x-show="scanNotice?.unknownCode" :href="'{{ route('products.create') }}?sku=' + encodeURIComponent(scanNotice?.unknownCode ?? '')"
                   class="font-medium underline">{{ __('Add product with this barcode') }}</a>
            @endif
        </div>
        @if ($tags->isNotEmpty())
            <div class="flex flex-wrap gap-1.5">
                @foreach ($tags as $tag)
                    <button type="button" @click="toggleTag({{ $tag->id }})" :class="selectedTags.includes({{ $tag->id }}) ? 'opacity-100' : 'opacity-50 hover:opacity-80'">
                        <x-tag :tag="$tag" x-bind:class="selectedTags.includes({{ $tag->id }}) && 'ring-2'" />
                    </button>
                @endforeach
            </div>
        @endif
    </div>
    <ul class="max-h-[28rem] divide-y divide-slate-100 overflow-y-auto">
        <template x-for="(p, i) in results" :key="p.id">
            <li>
                <button type="button" @click="add(p)" @mouseenter="highlighted = i"
                        :class="i === highlighted ? 'bg-emerald-50' : ''"
                        class="pick-row flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left active:bg-emerald-100">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-slate-900" x-text="p.name"></span>
                        <span class="flex flex-wrap gap-1 text-xs text-slate-500">
                            <span x-show="p.sku" x-text="p.sku"></span>
                            <template x-for="t in p.tags" :key="t.id"><span class="text-emerald-700" x-text="'#' + t.name"></span></template>
                        </span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="block text-sm font-semibold" x-text="money(p.selling_price)"></span>
                        <span class="block text-xs" :class="p.quantity <= 0 ? 'text-rose-600' : 'text-slate-500'" x-text="p.quantity + ' ' + p.unit"></span>
                    </span>
                </button>
            </li>
        </template>
        <li x-show="!loading && results.length === 0" class="space-y-2 px-4 py-8 text-center text-sm text-slate-500">
            <p>{{ __('No products found.') }}</p>
            @if (auth()->user()?->isOwner())
                <a href="{{ route('products.create') }}" class="btn-secondary inline-flex"><x-icon name="plus" class="h-4 w-4" /> {{ __('New product') }}</a>
            @else
                <p class="text-xs">{{ __('Ask the shop owner to add products first.') }}</p>
            @endif
        </li>
        <li x-show="loading" class="px-4 py-3 text-center text-xs text-slate-400">{{ __('Searching…') }}</li>
    </ul>
</div>
