<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Support\Decimal;
use App\Support\PrivateFile;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Encaissement total ou partiel ; jamais plus que le reste à payer (RG-13). Le statut suit.
 */
class RecordPayment
{
    public function handle(Invoice $invoice, string $amount, CarbonInterface $paidAt, PaymentMethod $method, ?UploadedFile $proof = null): Payment
    {
        if (Decimal::cmp($amount, 0) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être supérieur à 0.']);
        }

        $proofPath = PrivateFile::store($proof, 'proofs/payments');

        try {
            return DB::transaction(function () use ($invoice, $amount, $paidAt, $method, $proofPath) {
                $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

                if ($invoice->isCancelled()) {
                    throw ValidationException::withMessages(['amount' => 'Une facture annulée ne s’encaisse pas.']);
                }

                if (Decimal::cmp($amount, $invoice->remaining()) > 0) {
                    throw ValidationException::withMessages(['amount' => 'Le montant dépasse le reste à payer ('.number_format((float) $invoice->remaining(), 2, ',', ' ').' DA).']);
                }

                $payment = $invoice->payments()->create([
                    'client_id' => $invoice->client_id,
                    'amount' => $amount,
                    'paid_at' => $paidAt,
                    'payment_method_id' => $method->id,
                    'proof_path' => $proofPath,
                    'user_id' => Auth::id(),
                ]);

                $invoice->amount_paid = Decimal::add($invoice->amount_paid, $amount);
                $invoice->syncStatus();
                $invoice->save();

                return $payment;
            });
        } catch (Throwable $exception) {
            PrivateFile::delete($proofPath);

            throw $exception;
        }
    }
}
