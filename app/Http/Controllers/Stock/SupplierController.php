<?php

namespace App\Http\Controllers\Stock;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Decimal;
use App\Support\FormOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fournisseurs vus du stock : dette (reste dû des achats reçus) et commandes en cours.
 */
class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $suppliers = Supplier::query()
            ->withSum(['purchases as debt' => fn (Builder $query) => $query->where('status', PurchaseStatus::Received)], 'total')
            ->withSum(['purchases as debt_paid' => fn (Builder $query) => $query->where('status', PurchaseStatus::Received)], 'amount_paid')
            ->withCount(['purchases as open_orders' => fn (Builder $query) => $query->where('status', PurchaseStatus::Ordered)])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'debt' => Decimal::sub((string) ($supplier->debt ?? 0), (string) ($supplier->debt_paid ?? 0)),
                'open_orders' => $supplier->open_orders,
                'is_active' => $supplier->is_active,
            ]);

        return Inertia::render('suppliers/index', [
            'rows' => $suppliers,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Supplier $supplier): Response
    {
        $purchases = $supplier->purchases()->latest('purchased_at')->latest('id')->get();
        $received = $purchases->where('status', PurchaseStatus::Received);

        return Inertia::render('suppliers/show', [
            'supplier' => $supplier->only(['id', 'name', 'phone', 'email', 'address', 'maps_url', 'notes', 'is_active']),
            'totals' => [
                'purchased' => Decimal::add(...$received->pluck('total')->all()),
                'paid' => Decimal::add(...$received->pluck('amount_paid')->all()),
                'debt' => Decimal::add(...$received->map(fn (Purchase $purchase) => $purchase->remaining())->all()),
                'open_orders' => $purchases->where('status', PurchaseStatus::Ordered)->count(),
            ],
            'purchases' => $purchases->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'status' => $purchase->status->value,
                'status_label' => $purchase->status->label(),
                'purchased_at' => $purchase->purchased_at->toDateString(),
                'supplier_invoice_ref' => $purchase->supplier_invoice_ref,
                'total' => $purchase->total,
                'amount_paid' => $purchase->amount_paid,
                'remaining' => $purchase->remaining(),
                'is_payable' => $purchase->status->isPayable() && Decimal::cmp($purchase->remaining(), 0) > 0,
            ]),
            ...FormOptions::payment(),
        ]);
    }
}
