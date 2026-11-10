<?php

namespace App\Http\Controllers;

use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', Rule::in(array_keys(Shop::LOCALES))],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(6)],
        ]);

        $user->name = $data['name'];
        $user->locale = $data['locale'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        $request->session()->put('locale', $user->locale);

        return back()->with('success', __('Profile updated.'));
    }
}
