<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Services\NumberingService;
use App\Support\Decimal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Facture d'une prestation terminée : MO, pièces (celles du client à 0), total ; numéro FA-AAAA-00001 (RG-12).
 * Appelée par CompleteServiceOrder, dans la même transaction.
 */
class GenerateInvoice
{
    public function __construct(private NumberingService $numbering) {}

    public function handle(ServiceOrder $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            if ($order->invoice()->exists()) {
                throw ValidationException::withMessages(['status' => 'Cette prestation a déjà une facture.']);
            }

            $order->loadMissing(['lines', 'parts']);

            $invoice = new Invoice;
            $invoice->number = $this->numbering->invoice(today());
            $invoice->service_order_id = $order->id;
            $invoice->client_id = $order->client_id;
            $invoice->issued_at = today();
            $invoice->labor_total = $order->laborTotal();
            $invoice->parts_total = $order->partsTotal();
            $invoice->discount = '0.00';
            $invoice->total = Decimal::add($invoice->labor_total, $invoice->parts_total);
            $invoice->amount_paid = '0.00';
            $invoice->status = InvoiceStatus::Unpaid;
            $invoice->user_id = Auth::id();
            $invoice->syncStatus();
            $invoice->save();

            return $invoice;
        });
    }
}
