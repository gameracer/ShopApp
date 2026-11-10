@php $days = $product->daysToExpiry(); @endphp
@if ($days === null)
    <span class="text-xs text-slate-400">—</span>
@elseif ($days < 0)
    <span class="whitespace-nowrap rounded-full bg-rose-600 px-2 py-0.5 text-xs font-medium text-white" title="{{ $product->expiry_date->format('d/m/Y') }}">{{ __('Expired :days days ago', ['days' => abs($days)]) }}</span>
@elseif ($days === 0)
    <span class="whitespace-nowrap rounded-full bg-rose-600 px-2 py-0.5 text-xs font-medium text-white">{{ __('Expires today') }}</span>
@elseif ($days <= \App\Support\Shop::expiryAlertDays())
    <span class="whitespace-nowrap rounded-full {{ $days <= 7 ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }} px-2 py-0.5 text-xs font-medium" title="{{ $product->expiry_date->format('d/m/Y') }}">{{ __('In :days days', ['days' => $days]) }}</span>
@else
    <span class="whitespace-nowrap text-xs text-slate-500">{{ $product->expiry_date->format('d/m/Y') }}</span>
@endif
