<div class="overflow-x-auto">
    <table class="table">
        <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Tags') }}</th><th class="text-right">{{ __('In stock') }}</th><th>{{ __('Expiry date') }}</th><th>{{ __('Status') }}</th>@if (auth()->user()->isOwner())<th class="text-right">{{ __('Value at cost') }}</th>@endif</tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($items as $product)
            <tr>
                <td><a href="{{ route('products.show', $product) }}" class="font-medium hover:underline">{{ $product->name }}</a></td>
                <td><div class="flex flex-wrap gap-1">@foreach ($product->tags as $tag)<x-tag :tag="$tag" />@endforeach</div></td>
                <td class="text-right">{{ qty($product->quantity) }} {{ $product->unit }}</td>
                <td>{{ $product->expiry_date->format('d/m/Y') }}</td>
                <td>@include('products._expiry', ['product' => $product])</td>
                @if (auth()->user()->isOwner())<td class="text-right">{{ money($product->quantity * $product->buying_price) }}</td>@endif
            </tr>
        @empty
            <tr><td colspan="6"><x-empty>{{ $empty }}</x-empty></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
