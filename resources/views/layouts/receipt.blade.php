{{--
    Bare page for thermal receipt printers (58 mm / 80 mm rolls).
    No app CSS/JS: it is printed from a hidden window (desktop app) or a hidden iframe (browser),
    so it must render instantly and fit the printable width of the roll.
--}}
@php
    $paper = in_array($paper ?? null, \App\Models\Setting::RECEIPT_WIDTHS, true) ? $paper : '80';
    // Printable area is narrower than the roll: ~48 mm on 58 mm paper, ~72 mm on 80 mm paper.
    $printable = $paper === '58' ? 48 : 72;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { size: {{ $paper }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body {
            width: {{ $printable }}mm;
            margin: 0 auto;
            padding: 2mm 0 6mm;
            font: {{ $paper === '58' ? '11px' : '12px' }}/1.35 "Segoe UI", Arial, sans-serif;
            -webkit-print-color-adjust: exact;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .shop { font-size: 1.35em; font-weight: 700; }
        .small { font-size: .9em; }
        hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: .3mm 0; vertical-align: top; }
        .item td { padding-top: 1mm; }
        .total td { font-size: 1.25em; font-weight: 700; padding-top: 1mm; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body data-paper="{{ $paper }}">
@yield('content')
</body>
</html>
