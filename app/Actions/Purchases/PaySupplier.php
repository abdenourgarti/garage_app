<?php

namespace App\Actions\Purchases;

use App\Enums\SystemExpenseCategory;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Support\Decimal;
use App\Support\PrivateFile;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Paiement d'un achat : PurchasePayment + dépense « Achats pièces » (RG-14), plafonné au reste dû.
 */
class PaySupplier
{
    public function handle(Purchase $purchase, string $amount, CarbonInterface $paidAt, PaymentMethod $method, ?UploadedFile $proof = null): PurchasePayment
    {
        if (Decimal::cmp($amount, 0) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être supérieur à 0.']);
        }

        $proofPath = PrivateFile::store($proof, 'proofs/purchase-payments');

        try {
            return DB::transaction(function () use ($purchase, $amount, $paidAt, $method, $proofPath) {
                $purchase = Purchase::with('supplier')->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

                if (! $purchase->status->isPayable()) {
                    throw ValidationException::withMessages(['amount' => 'Un achat '.mb_strtolower($purchase->status->label()).' ne peut pas être payé.']);
                }

                if (Decimal::cmp($amount, $purchase->remaining()) > 0) {
                    throw ValidationException::withMessages(['amount' => 'Le montant dépasse le reste à payer ('.number_format((float) $purchase->remaining(), 2, ',', ' ').' DA).']);
                }

                $payment = $purchase->payments()->create([
                    'amount' => $amount,
                    'paid_at' => $paidAt,
                    'payment_method_id' => $method->id,
                    'proof_path' => $proofPath,
                    'user_id' => Auth::id(),
                ]);

                $purchase->amount_paid = Decimal::add($purchase->amount_paid, $amount);
                $purchase->save();

                $payment->expense()->create([
                    'expense_category_id' => ExpenseCategory::system(SystemExpenseCategory::Purchases)->id,
                    'label' => "Paiement {$purchase->supplier->name}, achat n° {$purchase->id}",
                    'amount' => $amount,
                    'spent_at' => $paidAt,
                    'payment_method_id' => $method->id,
                    'user_id' => Auth::id(),
                ]);

                return $payment;
            });
        } catch (Throwable $exception) {
            PrivateFile::delete($proofPath);

            throw $exception;
        }
    }
}
