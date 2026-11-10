<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with('user')
            ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->input('from'), fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->whereDate('expense_date', '<=', $d));

        return view('expenses.index', [
            'expenses' => (clone $query)->latest('expense_date')->latest('id')->paginate(30)->withQueryString(),
            'total' => (float) (clone $query)->sum('amount'),
            'byCategory' => (clone $query)->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category'),
        ]);
    }

    public function create()
    {
        return view('expenses.form', ['expense' => new Expense(['expense_date' => today()])]);
    }

    public function store(Request $request)
    {
        Expense::create($this->validated($request) + ['user_id' => $request->user()->id]);

        return redirect()->route('expenses.index')->with('success', __('Expense saved.'));
    }

    public function edit(Expense $expense)
    {
        return view('expenses.form', ['expense' => $expense]);
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($this->validated($request));

        return redirect()->route('expenses.index')->with('success', __('Expense saved.'));
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return back()->with('success', __('Expense deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'expense_date' => ['required', 'date'],
        ]);
    }
}
