<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::withBalance()
            ->withCount('sales')
            ->when($request->input('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('customers.index', ['customers' => $customers]);
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('success', __('Customer saved.'));
    }

    public function show(Customer $customer)
    {
        $sales = $customer->sales()->with('payments')->latest('sold_at')->get();

        return view('customers.show', [
            'customer' => $customer,
            'sales' => $sales,
            'balance' => round($sales->sum(fn ($s) => $s->balance()), 2),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request));

        return redirect()->route('customers.show', $customer)->with('success', __('Customer saved.'));
    }

    public function destroy(Customer $customer)
    {
        if ($customer->balance > 0) {
            return back()->withErrors(['customer' => __('Cannot delete a customer who still owes money.')]);
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', __('Customer deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
