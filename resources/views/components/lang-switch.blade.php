@props(['dark' => false])
<div {{ $attributes->class(['inline-flex overflow-hidden rounded-lg border text-xs font-semibold', $dark ? 'border-slate-700' : 'border-slate-300']) }}>
    @foreach (\App\Support\Shop::LOCALES as $code => $label)
        <a href="{{ route('locale.switch', $code) }}" title="{{ $label }}"
           @class([
               'px-2.5 py-1 uppercase',
               'bg-emerald-600 text-white' => app()->getLocale() === $code,
               ($dark ? 'text-slate-300 hover:bg-slate-800' : 'bg-white text-slate-600 hover:bg-slate-50') => app()->getLocale() !== $code,
           ])>{{ $code }}</a>
    @endforeach
</div>
