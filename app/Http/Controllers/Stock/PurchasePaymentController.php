<?php

namespace App\Http\Controllers\Stock;

use App\Actions\Purchases\PaySupplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchasePaymentController extends Controller
{
    public function store(PaymentRequest $request, Purchase $purchase, PaySupplier $action): RedirectResponse
    {
        $action->handle(
            $purchase,
            (string) $request->validated('amount'),
            Carbon::parse($request->validated('paid_at')),
            PaymentMethod::findOrFail($request->integer('payment_method_id')),
            $request->file('proof'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paiement enregistré (dépense « Achats pièces »).']);

        return back();
    }

    public function proof(PurchasePayment $payment): StreamedResponse
    {
        return PrivateFile::response($payment->proof_path);
    }
}
