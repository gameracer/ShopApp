<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

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
        ]);

        Setting::put(array_map(fn ($v) => $v ?? '', $data));

        return back()->with('success', __('Settings saved.'));
    }
}
