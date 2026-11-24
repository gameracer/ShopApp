<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const LAST_EMAIL_COOKIE = 'last_email';

    public function show(Request $request)
    {
        if (SetupController::needed()) {
            return redirect()->route('setup');
        }

        // The last email that logged in on this computer, so returning users only type their password.
        $lastEmail = $request->cookie(self::LAST_EMAIL_COOKIE);

        return view('auth.login', ['lastEmail' => is_string($lastEmail) ? $lastEmail : null]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        $request->session()->regenerate();
        $request->session()->put('locale', $request->user()->locale);

        return redirect()->intended(route('dashboard'))
            ->withCookie(cookie()->forever(self::LAST_EMAIL_COOKIE, $request->user()->email));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
