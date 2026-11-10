<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /**
     * Record a sale, deduct stock and register any initial payment.
     *
     * @param  array<int, array{product_id:int, quantity:float|string, unit_price?:float|string|null}>  $items
     */
    public function create(User $user, array $items, string $method, float $amountPaid, ?Customer $customer = null, ?string $dueDate = null): Sale
    {
        return DB::transaction(function () use ($user, $items, $method, $amountPaid, $customer, $dueDate) {
            $products = Product::whereIn('id', collect($items)->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            $total = 0.0;
            $cost = 0.0;

            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id']);
                $qty = round((float) $item['quantity'], 2);

                if (! $product) {
                    throw ValidationException::withMessages(["items.$index.product_id" => __('Product not found.')]);
                }

                if ($product->isExpired()) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => __(':name has expired and cannot be sold.', ['name' => $product->name]),
                    ]);
                }

                if ($qty > (float) $product->quantity) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => __('Only :qty :unit of :name left in stock.', [
                            'qty' => Shop::qty($product->quantity),
                            'unit' => $product->unit,
                            'name' => $product->name,
                        ]),
                    ]);
                }

                $price = isset($item['unit_price']) && $item['unit_price'] !== '' && $item['unit_price'] !== null
                    ? round((float) $item['unit_price'], 2)
                    : (float) $product->selling_price;

                $subtotal = round($price * $qty, 2);
                $total += $subtotal;
                $cost += round((float) $product->buying_price * $qty, 2);

                $lines[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'buying_price' => $product->buying_price,
                    'subtotal' => $subtotal,
                ];

                $product->quantity = round((float) $product->quantity - $qty, 2);
                $product->save();
            }

            $total = round($total, 2);
            $paid = $method === 'credit' ? min(max($amountPaid, 0), $total) : $total;

            if ($paid < $total && ! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => __('Select a customer for credit sales.'),
                ]);
            }

            $sale = Sale::create([
                'invoice_no' => Sale::nextInvoiceNo(),
                'user_id' => $user->id,
                'customer_id' => $customer?->id,
                'payment_method' => $method,
                'total' => $total,
                'cost_total' => round($cost, 2),
                'amount_paid' => $paid,
                'due_date' => $paid < $total ? $dueDate : null,
                'sold_at' => now(),
            ]);

            $sale->items()->createMany($lines);

            if ($paid > 0) {
                $sale->payments()->create([
                    'user_id' => $user->id,
                    'amount' => $paid,
                    'method' => $method === 'credit' ? 'cash' : $method,
                    'note' => $method === 'credit' ? __('Deposit') : null,
                    'paid_at' => now(),
                ]);
            }

            return $sale;
        });
    }

    /**
     * Record a repayment against a credit sale.
     */
    public function addPayment(Sale $sale, User $user, float $amount, string $method = 'cash', ?string $note = null): void
    {
        DB::transaction(function () use ($sale, $user, $amount, $method, $note) {
            $sale->refresh();
            $amount = round(min($amount, $sale->balance()), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => __('This sale is already fully paid.')]);
            }

            $sale->payments()->create([
                'user_id' => $user->id,
                'amount' => $amount,
                'method' => $method,
                'note' => $note,
                'paid_at' => now(),
            ]);

            $sale->update(['amount_paid' => round((float) $sale->amount_paid + $amount, 2)]);
        });
    }

    /**
     * Delete a sale and return its items to stock.
     */
    public function void(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            foreach ($sale->items as $item) {
                if ($item->product_id) {
                    Product::whereKey($item->product_id)->increment('quantity', (float) $item->quantity);
                }
            }

            $sale->delete();
        });
    }
}
