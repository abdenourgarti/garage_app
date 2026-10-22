<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseStatus;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Support\Decimal;
use App\Support\PrivateFile;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Création ou modification d'un achat (brouillon, commandé, ou reçu directement) :
 * lignes et total recalculés ; réception → stock ; montant payé → PaySupplier.
 */
class RecordPurchase
{
    public function __construct(
        private ReceivePurchase $receive,
        private PaySupplier $pay,
    ) {}

    /**
     * @param  array{supplier_id: int, purchased_at: string, supplier_invoice_ref?: string|null, notes?: string|null}  $data
     * @param  list<array{article_id: int, quantity: string, unit_price: string}>  $lines
     * @param  array{amount: string, paid_at: CarbonInterface, method: PaymentMethod, proof?: UploadedFile|null}|null  $payment
     */
    public function handle(?Purchase $purchase, array $data, array $lines, PurchaseStatus $status, ?UploadedFile $document = null, ?array $payment = null): Purchase
    {
        if ($purchase && ! $purchase->status->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Un achat '.mb_strtolower($purchase->status->label()).' ne peut plus être modifié.']);
        }

        if ($status === PurchaseStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => 'Utilisez « Annuler l’achat ».']);
        }

        if ($payment && $status === PurchaseStatus::Draft) {
            throw ValidationException::withMessages(['paid_amount' => 'Un brouillon ne se paie pas : passez la commande d’abord.']);
        }

        $documentPath = PrivateFile::store($document, 'documents/purchases');

        try {
            $purchase = DB::transaction(function () use ($purchase, $data, $lines, $status, $documentPath, $payment) {
                $purchase ??= new Purchase;
                $purchase->user_id ??= Auth::id();
                $purchase->amount_paid ??= '0.00';
                $purchase->fill($data);
                // « Reçu » directement : l'achat passe par l'état commandé, puis ReceivePurchase fait entrer le stock.
                $purchase->status = $status === PurchaseStatus::Received ? ($purchase->status ?? PurchaseStatus::Ordered) : $status;
                $oldDocument = $documentPath ? $purchase->document_path : null;
                $purchase->document_path = $documentPath ?? $purchase->document_path;
                $purchase->save();

                $purchase->items()->delete();

                foreach ($lines as $line) {
                    $purchase->items()->create([
                        'article_id' => $line['article_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'line_total' => Decimal::mul($line['quantity'], $line['unit_price']),
                    ]);
                }

                $purchase->total = Decimal::add(...$purchase->items()->pluck('line_total')->all());

                if (Decimal::cmp($purchase->amount_paid, $purchase->total) > 0) {
                    throw ValidationException::withMessages(['lines' => 'Le total ne peut pas être inférieur au montant déjà payé.']);
                }

                $purchase->save();

                if ($status === PurchaseStatus::Received) {
                    $purchase = $this->receive->handle($purchase);
                }

                if ($payment) {
                    $this->pay->handle($purchase, $payment['amount'], $payment['paid_at'], $payment['method'], $payment['proof'] ?? null);
                }

                PrivateFile::delete($oldDocument);

                return $purchase;
            });
        } catch (Throwable $exception) {
            PrivateFile::delete($documentPath);

            throw $exception;
        }

        return $purchase->refresh();
    }
}
