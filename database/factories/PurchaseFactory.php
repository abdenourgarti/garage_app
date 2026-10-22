<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Achat vide (sans lignes) : les achats réels passent par RecordPurchase.
 *
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseStatus::Draft,
            'purchased_at' => today(),
            'supplier_invoice_ref' => null,
            'total' => '0.00',
            'amount_paid' => '0.00',
            'user_id' => User::factory(),
        ];
    }
}
