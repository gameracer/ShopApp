@extends('layouts.app')
@section('title', __('Customers'))

@section('content')
<x-page-header :title="__('Customers')">
    <x-slot:actions>
        <a href="{{ route('customers.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('New customer') }}</a>
    </x-slot:actions>
</x-page-header>

<form class="mb-4 flex gap-2">
    <input name="q" value="{{ request('q') }}" class="input max-w-sm" placeholder="{{ __('Search name or phone') }}">
    <button class="btn-secondary">{{ __('Search') }}</button>
</form>

<div class="card overflow-x-auto">
    <table class="table">
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Address') }}</th><th class="text-right">{{ __('Purchases') }}</th><th class="text-right">{{ __('Balance') }}</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($customers as $customer)
            <tr class="cursor-pointer" onclick="location='{{ route('customers.show', $customer) }}'">
                <td class="font-medium">{{ $customer->name }}</td>
                <td>{{ $customer->phone ?? '—' }}</td>
                <td class="text-slate-500">{{ $customer->address ?? '—' }}</td>
                <td class="text-right">{{ $customer->sales_count }}</td>
                <td class="text-right {{ $customer->balance > 0 ? 'font-semibold text-rose-700' : 'text-slate-400' }}">{{ $customer->balance > 0 ? money($customer->balance, false) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty /></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $customers->links() }}</div>
@endsection
