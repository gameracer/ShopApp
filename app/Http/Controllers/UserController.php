<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Shop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::withCount('sales')->orderBy('role')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => User::ROLE_SELLER, 'locale' => 'sw', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        User::create($this->validated($request));

        return redirect()->route('users.index')->with('success', __('User saved.'));
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Never lock the shop out of its last active owner.
        if ($user->isOwner() && ($data['role'] !== User::ROLE_OWNER || ! $data['is_active']) && $this->lastOwner($user)) {
            return back()->withErrors(['role' => __('There must be at least one active owner.')])->withInput();
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', __('User saved.'));
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => __('You cannot delete your own account.')]);
        }

        if ($user->isOwner() && $this->lastOwner($user)) {
            return back()->withErrors(['user' => __('There must be at least one active owner.')]);
        }

        $user->delete();

        return back()->with('success', __('User deleted.'));
    }

    private function lastOwner(User $user): bool
    {
        return ! User::where('role', User::ROLE_OWNER)->where('is_active', true)->whereKeyNot($user->id)->exists();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::in(User::ROLES)],
            'locale' => ['required', Rule::in(array_keys(Shop::LOCALES))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(6)],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
