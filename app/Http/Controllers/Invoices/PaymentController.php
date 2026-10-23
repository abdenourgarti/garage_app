<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Invoices\RecordPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\SettingsService;
use App\Support\Decimal;
use App\Support\PrivateFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request, Invoice $invoice, RecordPayment $action): RedirectResponse
    {
        // Payé aujourd'hui : l'heure réelle ; une date passée : midi (l'ordre des paiements reste lisible).
        $date = Carbon::parse($request->validated('paid_at'));

        $action->handle(
            $invoice,
            (string) $request->validated('amount'),
            $date->isToday() ? now() : $date->setTime(12, 0),
            PaymentMethod::findOrFail($request->integer('payment_method_id')),
            $request->file('proof'),
        );

        $invoice->refresh();

        Inertia::flash('toast', ['type' => 'success', 'message' => Decimal::isZero($invoice->remaining())
            ? 'Paiement enregistré : la facture est payée.'
            : 'Paiement enregistré. Reste à payer : '.number_format((float) $invoice->remaining(), 0, ',', ' ').' DA.']);

        return back();
    }

    public function proof(Payment $payment): StreamedResponse
    {
        return PrivateFile::response($payment->proof_path);
    }

    /**
     * Reçu de paiement (A4, ticket 80 mm ou PDF).
     */
    public function receipt(Request $request, Payment $payment, string $format, SettingsService $settings): HttpResponse
    {
        $payment->load(['invoice.serviceOrder.vehicle', 'client', 'paymentMethod']);
        $invoice = $payment->invoice;

        // Payé avant ce reçu (paiements valides antérieurs), pour afficher le reste juste après ce paiement.
        $before = (string) $invoice->payments()
            ->whereNull('cancelled_at')
            ->where(fn ($query) => $query->where('paid_at', '<', $payment->paid_at)->orWhere(fn ($query) => $query->where('paid_at', $payment->paid_at)->where('id', '<', $payment->id)))
            ->sum('amount');

        return InvoiceController::render('documents.receipt', $format, "recu-{$invoice->number}-{$payment->id}", $request->boolean('print'), [
            'payment' => $payment,
            'invoice' => $invoice,
            'remainingAfter' => Decimal::sub($invoice->total, Decimal::add($before, $payment->amount)),
        ], $settings);
    }
}
