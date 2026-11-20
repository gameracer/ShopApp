<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * First-run setup: shown only while there are no users, so whoever installs the
 * shop chooses the shop name and the owner (and optionally a seller) account
 * instead of starting with shared default passwords.
 */
class SetupController extends Controller
{
    public static function needed(): bool
    {
        return ! User::query()->exists();
    }

    public function show()
    {
        abort_unless(static::needed(), 404);

        return view('auth.setup');
    }

    public function store(Request $request)
    {
        abort_unless(static::needed(), 404);

        $locales = Rule::in(array_keys(Shop::LOCALES));

        $data = $request->validate([
            'shop_name' => ['nullable', 'string', 'max:100'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'owner_password' => ['required', 'confirmed', Password::min(6)],
            'owner_locale' => ['required', $locales],
            'add_seller' => ['boolean'],
            'seller_name' => ['exclude_unless:add_seller,1', 'required', 'string', 'max:255'],
            'seller_email' => ['exclude_unless:add_seller,1', 'required', 'email', 'max:255', 'different:owner_email'],
            'seller_password' => ['exclude_unless:add_seller,1', 'required', 'confirmed', Password::min(6)],
            'seller_locale' => ['exclude_unless:add_seller,1', 'required', $locales],
        ]);

        $owner = DB::transaction(function () use ($data) {
            Setting::put(['shop_name' => filled($data['shop_name'] ?? null) ? $data['shop_name'] : Setting::DEFAULTS['shop_name']]);

            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
                'role' => User::ROLE_OWNER,
                'locale' => $data['owner_locale'],
                'is_active' => true,
            ]);

            if (isset($data['seller_email'])) {
                User::create([
                    'name' => $data['seller_name'],
                    'email' => $data['seller_email'],
                    'password' => $data['seller_password'],
                    'role' => User::ROLE_SELLER,
                    'locale' => $data['seller_locale'],
                    'is_active' => true,
                ]);
            }

            return $owner;
        });

        Auth::login($owner, true);
        $request->session()->regenerate();
        $request->session()->put('locale', $owner->locale);

        return redirect()->route('dashboard')->with('success', __('Setup complete. Welcome!'));
    }
}
