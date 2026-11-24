{{-- Owner's sales chart: daily (last 30 days), monthly (last 12 months) or yearly. resources/js/chart.js --}}
@php
    $periods = ['daily' => __('Daily'), 'monthly' => __('Monthly'), 'yearly' => __('Yearly')];
    $spans = ['daily' => __('Last 30 days'), 'monthly' => __('Last 12 months'), 'yearly' => __('All years')];
    [$oneSale, $manySales] = array_pad(explode('|', __(':count sale|:count sales')), 2, null);
@endphp
<div class="card mt-6" x-data="salesChart(@js(['currency' => \App\Support\Shop::currency(), 'series' => $trend]))">
    <div class="card-header flex-wrap">
        <div>
            <h2 class="card-title">{{ __('Sales') }}</h2>
            <p class="text-xs text-slate-500">
                <span x-text="@js($spans)[period]"></span> ·
                <span class="font-medium text-slate-700" x-text="money(sum)"></span> ·
                <span x-text="(count === 1 ? @js($oneSale) : @js($manySales ?? $oneSale)).replace(':count', count.toLocaleString())"></span>
            </p>
        </div>
        <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs font-medium" role="group" aria-label="{{ __('Sales') }}">
            @foreach ($periods as $key => $name)
                <button type="button" @click="setPeriod('{{ $key }}')" :aria-pressed="period === '{{ $key }}'"
                        class="rounded-md px-3 py-1.5 transition-colors"
                        :class="period === '{{ $key }}' ? 'bg-white text-emerald-700 shadow-sm ring-1 ring-slate-200' : 'text-slate-500 hover:text-slate-700'">{{ $name }}</button>
            @endforeach
        </div>
    </div>

    <div class="p-4">
        <div class="flex gap-2">
            {{-- Y axis --}}
            <div class="relative h-56 w-12 shrink-0 text-right text-[11px] text-slate-400 tabular-nums" aria-hidden="true">
                <template x-for="(t, i) in ticks" :key="i">
                    <span class="absolute right-0" :style="`bottom: ${i * 25}%; transform: translateY(50%)`" x-text="compact(t)"></span>
                </template>
            </div>

            <div class="min-w-0 flex-1">
                {{-- Plot --}}
                <div class="relative h-56" @mouseleave="active = null">
                    <template x-for="(t, i) in ticks" :key="i">
                        <div class="absolute inset-x-0 border-t" :class="i === 0 ? 'border-slate-300' : 'border-dashed border-slate-100'" :style="`bottom: ${i * 25}%`" aria-hidden="true"></div>
                    </template>

                    <div class="absolute inset-0 flex items-end gap-[2px]">
                        <template x-for="(p, i) in points" :key="period + i">
                            <button type="button" class="group flex h-full min-w-0 flex-1 items-end focus:outline-none"
                                    @mouseenter="active = i" @focus="active = i" @blur="active = null"
                                    :aria-label="`${p.label}: ${money(p.total)}`">
                                <span class="block w-full rounded-t-[4px] transition-colors"
                                      :class="active === i ? 'bg-emerald-700' : 'bg-emerald-600'"
                                      :style="`height: ${height(p)}; min-height: ${p.total > 0 ? '2px' : '0'}`"></span>
                            </button>
                        </template>
                    </div>

                    {{-- Tooltip --}}
                    <template x-if="active !== null && points[active]">
                        <div class="pointer-events-none absolute -top-2 z-10 -translate-y-full rounded-lg bg-slate-900 px-3 py-2 text-xs whitespace-nowrap text-white shadow-lg"
                             :style="tipStyle(active)">
                            <p class="text-slate-300" x-text="points[active].label"></p>
                            <p class="text-sm font-semibold tabular-nums" x-text="money(points[active].total)"></p>
                            <p class="text-slate-300" x-text="(points[active].count === 1 ? @js($oneSale) : @js($manySales ?? $oneSale)).replace(':count', points[active].count.toLocaleString())"></p>
                        </div>
                    </template>
                </div>

                {{-- X axis --}}
                <div class="mt-1.5 flex gap-[2px] text-[11px] text-slate-400" aria-hidden="true">
                    <template x-for="(p, i) in points" :key="period + i">
                        <span class="min-w-0 flex-1 text-center whitespace-nowrap" :class="active === i && 'font-medium text-slate-700'">
                            <span x-show="showLabel(i) || active === i" x-text="p.short"></span>
                        </span>
                    </template>
                </div>
            </div>
        </div>

        {{-- The same figures as a table for screen readers --}}
        <table class="sr-only">
            <caption x-text="@js(__('Sales')) + ' · ' + @js($spans)[period]"></caption>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Total') }}</th></tr></thead>
            <tbody>
                <template x-for="(p, i) in points" :key="period + i">
                    <tr><td x-text="p.label"></td><td x-text="money(p.total)"></td></tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
