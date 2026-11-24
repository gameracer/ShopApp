@extends('layouts.app')
@section('title', __('Users'))

@section('content')
<x-page-header :title="__('Users')" :subtitle="__('Owners have full access. Sellers can sell and manage credit customers.')">
    <x-slot:actions>
        <a href="{{ route('users.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('New user') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Language') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Sales') }}</th><th></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($users as $user)
            <tr>
                <td class="font-medium">{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $user->isOwner() ? 'bg-violet-100 text-violet-800' : 'bg-sky-100 text-sky-800' }}">{{ __(ucfirst($user->role)) }}</span></td>
                <td>{{ \App\Support\Shop::LOCALES[$user->locale] ?? $user->locale }}</td>
                <td>{!! $user->is_active ? '<span class="text-emerald-700">'.e(__('Active')).'</span>' : '<span class="text-slate-400">'.e(__('Inactive')).'</span>' !!}</td>
                <td class="text-right">{{ $user->sales_count }}</td>
                <td class="text-right whitespace-nowrap">
                    <a href="{{ route('users.edit', $user) }}" class="btn-secondary btn-sm">{{ __('Edit') }}</a>
                    @unless ($user->is(auth()->user()))
                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('{{ __('Delete this user?') }}')">
                            @csrf @method('DELETE')
                            <button class="btn-secondary btn-sm text-rose-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </form>
                    @endunless
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
