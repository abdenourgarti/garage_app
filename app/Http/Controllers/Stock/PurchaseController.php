<?php

namespace App\Http\Controllers\Stock;

use App\Actions\Purchases\CancelPurchase;
use App\Actions\Purchases\ReceivePurchase;
use App\Actions\Purchases\RecordPurchase;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\PurchaseRequest;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Support\FormOptions;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $status = PurchaseStatus::tryFrom($request->string('status')->toString());
        $supplierId = $request->integer('supplier') ?: null;
        $search = trim($request->string('search')->toString());

        $purchases = Purchase::query()
            ->with('supplier:id,name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('supplier_invoice_ref', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('items.article', fn ($query) => $query->where('name', 'like', "%{$search}%"))))
            ->latest('purchased_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Purchase $purchase) => self::summary($purchase));

        return Inertia::render('purchases/index', [
            'rows' => $purchases,
            'filters' => ['search' => $search, 'status' => $status?->value, 'supplier' => $supplierId],
            'statuses' => PurchaseStatus::options(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('purchases/form', [
            'purchase' => null,
            'defaultSupplierId' => $request->integer('supplier') ?: null,
            ...self::formOptions(),
        ]);
    }

    public function store(PurchaseRequest $request, RecordPurchase $action): RedirectResponse
    {
        $purchase = $action->handle(null, $request->purchaseData(), $request->lines(), PurchaseStatus::from($request->validated('status')), $request->file('document'), $request->payment());

        Inertia::flash('toast', ['type' => 'success', 'message' => self::savedMessage($purchase)]);

        return to_route('purchases.show', $purchase);
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load(['supplier:id,name,phone', 'items.article:id,name,stock_unit', 'payments.paymentMethod:id,name', 'payments.user:id,name', 'user:id,name']);

        return Inertia::render('purchases/show', [
            'purchase' => [
                ...self::summary($purchase),
                'supplier' => $purchase->supplier->only(['id', 'name', 'phone']),
                'received_at' => $purchase->received_at?->toDateString(),
                'notes' => $purchase->notes,
                'document_url' => $purchase->document_path ? route('purchases.document', $purchase) : null,
                'user' => $purchase->user->name,
                'is_editable' => $purchase->status->isEditable(),
                'is_payable' => $purchase->status->isPayable(),
                'items' => $purchase->items->map(fn (PurchaseItem $item) => [
                    'id' => $item->id,
                    'article_id' => $item->article_id,
                    'name' => $item->article->name,
                    'unit' => $item->article->stock_unit->symbol(),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]),
                'payments' => $purchase->payments->map(fn (PurchasePayment $payment) => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at->toDateString(),
                    'method' => $payment->paymentMethod->name,
                    'user' => $payment->user->name,
                    'proof_url' => $payment->proof_path ? route('purchase-payments.proof', $payment) : null,
                ]),
            ],
            ...FormOptions::payment(),
        ]);
    }

    public function edit(Purchase $purchase): Response|RedirectResponse
    {
        if (! $purchase->status->isEditable()) {
            return to_route('purchases.show', $purchase);
        }

        $purchase->load(['supplier:id,name', 'items.article:id,name,stock_unit']);

        return Inertia::render('purchases/form', [
            'purchase' => [
                ...self::summary($purchase),
                'supplier_id' => $purchase->supplier_id,
                'notes' => $purchase->notes,
                'has_document' => $purchase->document_path !== null,
                'items' => $purchase->items->map(fn (PurchaseItem $item) => [
                    'article' => ['value' => (string) $item->article_id, 'label' => $item->article->name, 'unit' => $item->article->stock_unit->symbol()],
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]),
            ],
            'defaultSupplierId' => null,
            ...self::formOptions(),
        ]);
    }

    public function update(PurchaseRequest $request, Purchase $purchase, RecordPurchase $action): RedirectResponse
    {
        $purchase = $action->handle($purchase, $request->purchaseData(), $request->lines(), PurchaseStatus::from($request->validated('status')), $request->file('document'), $request->payment());

        Inertia::flash('toast', ['type' => 'success', 'message' => self::savedMessage($purchase)]);

        return to_route('purchases.show', $purchase);
    }

    public function receive(Purchase $purchase, ReceivePurchase $action): RedirectResponse
    {
        $action->handle($purchase);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Achat reçu : le stock est à jour.']);

        return back();
    }

    public function cancel(Purchase $purchase, CancelPurchase $action): RedirectResponse
    {
        $action->handle($purchase);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Achat annulé.']);

        return back();
    }

    public function document(Purchase $purchase): StreamedResponse
    {
        return PrivateFile::response($purchase->document_path);
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(Purchase $purchase): array
    {
        return [
            'id' => $purchase->id,
            'supplier_name' => $purchase->supplier->name,
            'status' => $purchase->status->value,
            'status_label' => $purchase->status->label(),
            'purchased_at' => $purchase->purchased_at->toDateString(),
            'supplier_invoice_ref' => $purchase->supplier_invoice_ref,
            'total' => $purchase->total,
            'amount_paid' => $purchase->amount_paid,
            'remaining' => $purchase->remaining(),
            'payment_status' => $purchase->status === PurchaseStatus::Cancelled ? null : $purchase->paymentStatus(),
        ];
    }

    private static function savedMessage(Purchase $purchase): string
    {
        return match ($purchase->status) {
            PurchaseStatus::Draft => 'Brouillon enregistré.',
            PurchaseStatus::Ordered => 'Commande enregistrée.',
            default => 'Achat reçu : le stock est à jour.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function formOptions(): array
    {
        return [
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            ...FormOptions::payment(),
        ];
    }
}
