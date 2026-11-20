<hr>
@if ($footer = \App\Models\Setting::get('receipt_footer'))
    <div class="center small">{{ $footer }}</div>
@endif
<div class="center small">{{ __('Thank you for shopping with us!') }}</div>
