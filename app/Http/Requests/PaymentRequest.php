<?php

namespace App\Http\Requests;

use App\Support\PrivateFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            'proof' => PrivateFile::RULES,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => 'montant',
            'paid_at' => 'date du paiement',
            'payment_method_id' => 'mode de paiement',
            'proof' => 'preuve de paiement',
        ];
    }
}
