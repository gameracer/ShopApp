<div class="center">
    <div class="shop">{{ \App\Support\Shop::name() }}</div>
    @if ($address = \App\Models\Setting::get('shop_address'))<div class="small">{{ $address }}</div>@endif
    @if ($phone = \App\Models\Setting::get('shop_phone'))<div class="small">{{ __('Tel') }}: {{ $phone }}</div>@endif
</div>
<hr>
