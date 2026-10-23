<?php

namespace App\Actions\ServiceOrders;

use App\Actions\Invoices\GenerateInvoice;
use App\Enums\PartOrigin;
use App\Enums\ServiceOrderStatus;
use App\Enums\StockMovementType;
use App\Enums\SystemExpenseCategory;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ServiceOrder;
use App\Services\StockService;
use App\Support\Decimal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * « Terminer » : contrôles RG-06 / RG-07, sorties de stock à coût figé (RG-08), dépenses des pièces
 * achetées à l'extérieur, dernier kilométrage du véhicule, statut Terminée, facture (GenerateInvoice).
 * Les alertes (stock insuffisant RG-11, kilométrage plus bas RG-06) ne bloquent pas : elles sont renvoyées.
 */
class CompleteServiceOrder
{
    public function __construct(
        private StockService $stock,
        private GenerateInvoice $generateInvoice,
    ) {}

    /**
     * @return list<string> non-blocking warnings
     */
    public function handle(ServiceOrder $order): array
    {
        return DB::transaction(function () use ($order) {
            $order = ServiceOrder::with(['lines.serviceType', 'parts.article', 'vehicle'])->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->ensureCompletable($order);

            $warnings = [];
            $vehicle = $order->vehicle;

            if ($order->mileage !== null && $vehicle->last_mileage !== null && $order->mileage < $vehicle->last_mileage) {
                $warnings[] = 'Kilométrage inférieur au dernier relevé ('.number_format($vehicle->last_mileage, 0, ',', ' ').' km) : le dernier relevé est conservé.';
            }

            foreach ($order->parts as $part) {
                if ($part->origin === PartOrigin::Stock) {
                    $article = $part->article;

                    if (Decimal::cmp($article->fresh()->stock_qty, $part->quantity) < 0) {
                        $warnings[] = "Stock insuffisant pour {$article->name} : vérifiez le stock et faites une correction si besoin.";
                    }

                    $this->stock->out($article, $part->quantity, StockMovementType::ServiceOut, $article->purchase_price, $order);
                    $part->update(['unit_cost' => $article->purchase_price]);
                }
            }

            $this->recordExternalPartsExpense($order);

            // Une faute de frappe plus basse ne doit pas fausser les rappels de vidange.
            if ($order->mileage !== null && ($vehicle->last_mileage === null || $order->mileage > $vehicle->last_mileage)) {
                $vehicle->update(['last_mileage' => $order->mileage]);
            }

            $order->status = ServiceOrderStatus::Completed;
            $order->completed_at = now();
            $order->save();

            $this->generateInvoice->handle($order);

            return $warnings;
        });
    }

    /**
     * Une seule dépense « Pièces extérieures » pour ce qui n'a pas encore été dépensé sur cette prestation :
     * après une annulation de facture, la pompe à eau déjà payée n'est pas comptée deux fois.
     */
    private function recordExternalPartsExpense(ServiceOrder $order): void
    {
        $external = $order->parts->where('origin', PartOrigin::External)->filter(fn ($part) => Decimal::cmp((string) $part->unit_cost, 0) > 0);
        $cost = Decimal::add(...$external->map(fn ($part) => Decimal::mul($part->quantity, $part->unit_cost))->all());
        $missing = Decimal::sub($cost, (string) $order->expenses()->sum('amount'));

        if (Decimal::cmp($missing, 0) <= 0) {
            return;
        }

        $order->expenses()->create([
            'expense_category_id' => ExpenseCategory::system(SystemExpenseCategory::ExternalParts)->id,
            'label' => 'Pièces extérieures : '.$external->pluck('designation')->implode(', ')." ({$order->number})",
            'amount' => $missing,
            'spent_at' => today(),
            'payment_method_id' => PaymentMethod::active()->where('is_cash', true)->value('id'),
            'user_id' => Auth::id(),
        ]);
    }

    private function ensureCompletable(ServiceOrder $order): void
    {
        if (! $order->isInProgress()) {
            throw ValidationException::withMessages(['status' => 'Cette prestation est déjà terminée.']);
        }

        if ($order->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Ajoutez au moins un travail.']);
        }

        $errors = [];
        $needsMileage = $order->lines->filter(fn ($line) => $line->serviceType->requires_mileage);

        if ($order->mileage === null && $needsMileage->isNotEmpty()) {
            $errors['mileage'] = 'Le kilométrage est obligatoire pour : '.$needsMileage->pluck('serviceType.name')->unique()->implode(', ').'.';
        }

        foreach ($order->lines->values() as $index => $line) {
            if ($line->employee_id === null) {
                $errors["lines.{$index}.employee_id"] = 'Choisissez le mécanicien.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
