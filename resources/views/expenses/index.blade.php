@extends('layouts.app')
@section('title', __('Expenses'))

@section('content')
<x-page-header :title="__('Expenses')" :subtitle="__('Electricity bills, food, salaries and other shop costs.')">
    <x-slot:actions>
        <a href="{{ route('expenses.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4" /> {{ __('Add expense') }}</a>
    </x-slot:actions>
</x-page-header>

<form class="card mb-4 grid gap-2 p-4 sm:grid-cols-5">
    <select name="category" class="input">
        <option value="">{{ __('All categories') }}</option>
        @foreach (\App\Models\Expense::CATEGORIES as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ __(ucfirst($c)) }}</option>@endforeach
    </select>
    <input type="date" name="from" value="{{ request('from') }}" class="input" title="{{ __('From') }}">
    <input type="date" name="to" value="{{ request('to') }}" class="input" title="{{ __('To') }}">
    <button class="btn-secondary">{{ __('Filter') }}</button>
    @if (request()->hasAny(['category', 'from', 'to']))<a href="{{ route('expenses.index') }}" class="btn-secondary">{{ __('Clear') }}</a>@endif
</form>

<div class="grid gap-6 lg:grid-cols-4">
    <div class="card h-fit p-4 lg:order-last">
        <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">{{ __('Total') }}</p>
        <p class="mb-3 text-2xl font-bold">{{ money($total) }}</p>
        <ul class="space-y-2 text-sm">
            @foreach ($byCategory as $category => $sum)
                <li>
                    <div class="flex justify-between"><span>{{ __(ucfirst($category)) }}</span><span class="font-medium">{{ money($sum, false) }}</span></div>
                    <div class="mt-1 h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded bg-emerald-500" style="width: {{ $total > 0 ? round($sum / $total * 100) : 0 }}%"></div></div>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="card overflow-x-auto lg:col-span-3">
        <table class="table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Category') }}</th><th>{{ __('Description') }}</th><th class="text-right">{{ __('Amount') }}</th><th></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($expenses as $expense)
                <tr>
                    <td class="whitespace-nowrap">{{ $expense->expense_date->format('d/m/Y') }}</td>
                    <td><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium">{{ __(ucfirst($expense->category)) }}</span></td>
                    <td>{{ $expense->description ?? '—' }}<div class="text-xs text-slate-400">{{ $expense->user?->name }}</div></td>
                    <td class="text-right font-medium">{{ money($expense->amount, false) }}</td>
                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('expenses.edit', $expense) }}" class="btn-secondary btn-sm">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="inline" onsubmit="return confirm('{{ __('Delete this expense?') }}')">
                            @csrf @method('DELETE')
                            <button class="btn-secondary btn-sm text-rose-600"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty>{{ __('No expenses recorded.') }}</x-empty></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $expenses->links() }}</div>
@endsection
