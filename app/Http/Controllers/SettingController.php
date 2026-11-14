<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function edit()
    {
        return view('settings', [
            'settings' => collect(Setting::DEFAULTS)->mapWithKeys(fn ($default, $key) => [$key => Setting::get($key)]),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:100'],
            'shop_phone' => ['nullable', 'string', 'max:50'],
            'shop_address' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'expiry_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'receipt_width' => ['required', Rule::in(Setting::RECEIPT_WIDTHS)],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
        ]);

        $data['auto_print_receipt'] = $request->boolean('auto_print_receipt') ? '1' : '0';
        $data['scanner_beep'] = $request->boolean('scanner_beep') ? '1' : '0';

        Setting::put(array_map(fn ($v) => $v ?? '', $data));

        return back()->with('success', __('Settings saved.'));
    }

    /**
     * Sample receipt for checking the printer, paper width and alignment.
     */
    public function testReceipt(Request $request)
    {
        $width = in_array($request->query('width'), Setting::RECEIPT_WIDTHS, true)
            ? $request->query('width')
            : Setting::get('receipt_width');

        return view('sales.receipt-test', ['paper' => $width]);
    }
}
