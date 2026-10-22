<?php

namespace App\Http\Requests\Stock;

use App\Enums\PurchaseStatus;
use App\Models\PaymentMethod;
use App\Support\Decimal;
use App\Support\PrivateFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PurchaseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'purchased_at' => ['required', 'date', 'before_or_equal:today'],
            'supplier_invoice_ref' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in([PurchaseStatus::Draft->value, PurchaseStatus::Ordered->value, PurchaseStatus::Received->value])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.article_id' => ['required', 'integer', Rule::exists('articles', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'document' => PrivateFile::RULES,
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'proof' => PrivateFile::RULES,
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->hasPayment() && ! $this->filled('payment_method_id')) {
                $validator->errors()->add('payment_method_id', 'Choisissez le mode de paiement.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'fournisseur',
            'purchased_at' => "date d'achat",
            'supplier_invoice_ref' => 'n° de facture du fournisseur',
            'lines' => 'articles',
            'lines.*.article_id' => 'article',
            'lines.*.quantity' => 'quantité',
            'lines.*.unit_price' => 'prix unitaire',
            'document' => 'document',
            'paid_amount' => 'montant payé',
            'payment_method_id' => 'mode de paiement',
            'paid_at' => 'date du paiement',
            'proof' => 'preuve de paiement',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['lines.required' => 'Ajoutez au moins un article.', 'lines.min' => 'Ajoutez au moins un article.'];
    }

    public function hasPayment(): bool
    {
        return $this->filled('paid_amount') && Decimal::cmp((string) $this->input('paid_amount'), 0) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseData(): array
    {
        return $this->safe()->only(['supplier_id', 'purchased_at', 'supplier_invoice_ref', 'notes']);
    }

    /**
     * @return list<array{article_id: int, quantity: string, unit_price: string}>
     */
    public function lines(): array
    {
        return array_values($this->validated('lines'));
    }

    /**
     * @return array{amount: string, paid_at: Carbon, method: PaymentMethod, proof: mixed}|null
     */
    public function payment(): ?array
    {
        return $this->hasPayment() ? [
            'amount' => (string) $this->validated('paid_amount'),
            'paid_at' => Carbon::parse($this->validated('paid_at') ?? today()),
            'method' => PaymentMethod::findOrFail($this->integer('payment_method_id')),
            'proof' => $this->file('proof'),
        ] : null;
    }
}
