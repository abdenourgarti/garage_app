<?php

namespace App\Enums;

use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\Energy;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Models\Wilaya;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Les référentiels gérés par l'écran Paramètres > Référentiels.
 * La valeur sert de segment d'URL : /admin/references/{type}.
 */
enum ReferenceType: string
{
    case Brands = 'brands';
    case VehicleModels = 'vehicle-models';
    case Energies = 'energies';
    case ServiceTypes = 'service-types';
    case ArticleCategories = 'article-categories';
    case Suppliers = 'suppliers';
    case ExpenseCategories = 'expense-categories';
    case PaymentMethods = 'payment-methods';
    case Wilayas = 'wilayas';

    public function label(): string
    {
        return match ($this) {
            self::Brands => 'Marques',
            self::VehicleModels => 'Modèles',
            self::Energies => 'Énergies',
            self::ServiceTypes => 'Types de prestations',
            self::ArticleCategories => "Catégories d'articles",
            self::Suppliers => 'Fournisseurs',
            self::ExpenseCategories => 'Catégories de dépenses',
            self::PaymentMethods => 'Modes de paiement',
            self::Wilayas => 'Wilayas',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return match ($this) {
            self::Brands => Brand::class,
            self::VehicleModels => VehicleModel::class,
            self::Energies => Energy::class,
            self::ServiceTypes => ServiceType::class,
            self::ArticleCategories => ArticleCategory::class,
            self::Suppliers => Supplier::class,
            self::ExpenseCategories => ExpenseCategory::class,
            self::PaymentMethods => PaymentMethod::class,
            self::Wilayas => Wilaya::class,
        };
    }

    /**
     * @return list<string>
     */
    public function searchColumns(): array
    {
        return match ($this) {
            self::Wilayas => ['code', 'name'],
            self::Suppliers => ['name', 'phone'],
            default => ['name'],
        };
    }

    public function orderColumn(): string
    {
        return $this === self::Wilayas ? 'code' : 'name';
    }

    /**
     * Validation rules for create (null) or update ($id).
     *
     * @return array<string, mixed>
     */
    public function rules(?int $id, ?int $brandId = null): array
    {
        $table = (new ($this->model()))->getTable();
        $uniqueName = Rule::unique($table, 'name')->ignore($id);

        return match ($this) {
            self::Wilayas => [
                'code' => ['required', 'digits:2', Rule::unique($table, 'code')->ignore($id)],
                'name' => ['required', 'string', 'max:100'],
            ],
            self::VehicleModels => [
                'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')],
                'name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')->where('brand_id', $brandId)->ignore($id)],
                'oil_capacity_l' => ['nullable', 'numeric', 'min:0.5', 'max:99'],
            ],
            self::ServiceTypes => [
                'name' => ['required', 'string', 'max:100', $uniqueName],
                'default_labor_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
                'requires_mileage' => ['required', 'boolean'],
                'reminder_km' => ['nullable', 'integer', 'min:100', 'max:1000000'],
            ],
            self::Suppliers => [
                'name' => ['required', 'string', 'max:150'],
                'phone' => ['nullable', 'string', 'max:30'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            self::PaymentMethods => [
                'name' => ['required', 'string', 'max:100', $uniqueName],
                'is_cash' => ['required', 'boolean'],
            ],
            default => [
                'name' => ['required', 'string', 'max:100', $uniqueName],
            ],
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
