<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use App\Support\Normalize;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Client|null $client */
        $client = $this->route('client');

        return self::clientRules($client?->id);
    }

    /**
     * Shared with the quick creation form.
     *
     * @return array<string, array<mixed>>
     */
    public static function clientRules(?int $ignoreId = null): array
    {
        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) use ($ignoreId) {
                $normalized = Normalize::phone((string) $value);

                if (strlen($normalized) < 9 || strlen($normalized) > 13) {
                    $fail('Le numéro de téléphone doit contenir entre 9 et 13 chiffres.');

                    return;
                }

                $other = Client::where('phone_normalized', $normalized)
                    ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                    ->first();

                if ($other) {
                    $fail("Ce numéro appartient déjà au client {$other->full_name}.");
                }
            }],
            'wilaya_id' => ['nullable', 'integer', Rule::exists('wilayas', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::clientAttributes();
    }

    /**
     * @return array<string, string>
     */
    public static function clientAttributes(): array
    {
        return [
            'last_name' => 'nom',
            'first_name' => 'prénom',
            'phone' => 'téléphone',
            'wilaya_id' => 'wilaya',
            'notes' => 'remarque',
        ];
    }
}
