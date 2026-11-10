@extends('layouts.app')
@section('title', $expense->exists ? __('Edit expense') : __('Add expense'))

@section('content')
<x-page-header :title="$expense->exists ? __('Edit expense') : __('Add expense')" />

<form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" class="card max-w-xl space-y-4 p-6">
    @csrf
    @if ($expense->exists) @method('PUT') @endif
    <div>
        <span class="label">{{ __('Category') }} *</span>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach (\App\Models\Expense::CATEGORIES as $c)
                <label class="cursor-pointer">
                    <input type="radio" name="category" value="{{ $c }}" class="peer hidden" @checked(old('category', $expense->category ?? 'electricity') === $c)>
                    <span class="block rounded-lg border border-slate-300 px-3 py-2 text-center text-sm peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:font-medium peer-checked:text-emerald-800">{{ __(ucfirst($c)) }}</span>
                </label>
            @endforeach
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label">{{ __('Amount') }} *</label>
            <input name="amount" type="number" step="0.01" min="0.01" class="input" value="{{ old('amount', $expense->amount) }}" required>
        </div>
        <div>
            <label class="label">{{ __('Date') }} *</label>
            <input name="expense_date" type="date" class="input" value="{{ old('expense_date', $expense->expense_date?->toDateString()) }}" required>
        </div>
    </div>
    <div>
        <label class="label">{{ __('Description') }}</label>
        <input name="description" class="input" value="{{ old('description', $expense->description) }}" placeholder="{{ __('e.g. LUKU tokens for November') }}">
    </div>
    <div class="flex gap-2">
        <button class="btn-primary">{{ __('Save') }}</button>
        <a href="{{ route('expenses.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
