<?php

namespace Database\Seeders;

use App\Enums\SystemExpenseCategory;
use App\Enums\UserRole;
use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\Energy;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\VehicleModel;
use App\Models\Wilaya;
use Illuminate\Database\Seeder;

/**
 * Données livrées avec chaque installation (docs/02-conception-technique.md §13).
 * Idempotent : relancé, il complète sans dupliquer ni écraser ce que le gérant a modifié.
 */
class ReferenceDataSeeder extends Seeder
{
    /**
     * Wilayas : 48 historiques, 10 créées en 2019 (49–58) et 11 créées par la loi 26-06,
     * numérotées 59–69 par le décret présidentiel 26-206 du 25 mai 2026 (JO n° 40).
     *
     * @var array<string, string>
     */
    private const WILAYAS = [
        '01' => 'Adrar', '02' => 'Chlef', '03' => 'Laghouat', '04' => 'Oum El Bouaghi', '05' => 'Batna',
        '06' => 'Béjaïa', '07' => 'Biskra', '08' => 'Béchar', '09' => 'Blida', '10' => 'Bouira',
        '11' => 'Tamanrasset', '12' => 'Tébessa', '13' => 'Tlemcen', '14' => 'Tiaret', '15' => 'Tizi Ouzou',
        '16' => 'Alger', '17' => 'Djelfa', '18' => 'Jijel', '19' => 'Sétif', '20' => 'Saïda',
        '21' => 'Skikda', '22' => 'Sidi Bel Abbès', '23' => 'Annaba', '24' => 'Guelma', '25' => 'Constantine',
        '26' => 'Médéa', '27' => 'Mostaganem', '28' => "M'Sila", '29' => 'Mascara', '30' => 'Ouargla',
        '31' => 'Oran', '32' => 'El Bayadh', '33' => 'Illizi', '34' => 'Bordj Bou Arréridj', '35' => 'Boumerdès',
        '36' => 'El Tarf', '37' => 'Tindouf', '38' => 'Tissemsilt', '39' => 'El Oued', '40' => 'Khenchela',
        '41' => 'Souk Ahras', '42' => 'Tipaza', '43' => 'Mila', '44' => 'Aïn Defla', '45' => 'Naâma',
        '46' => 'Aïn Témouchent', '47' => 'Ghardaïa', '48' => 'Relizane', '49' => 'Timimoun', '50' => 'Bordj Badji Mokhtar',
        '51' => 'Ouled Djellal', '52' => 'Béni Abbès', '53' => 'In Salah', '54' => 'In Guezzam', '55' => 'Touggourt',
        '56' => 'Djanet', '57' => "El M'Ghair", '58' => 'El Meniaa', '59' => 'Aflou', '60' => 'Barika',
        '61' => 'El Kantara', '62' => 'Bir El Ater', '63' => 'El Aricha', '64' => 'Ksar Chellala', '65' => 'Aïn Ouessara',
        '66' => 'Messaad', '67' => 'Ksar El Boukhari', '68' => 'Bou Saâda', '69' => 'El Abiodh Sidi Cheikh',
    ];

    private const ENERGIES = ['Essence', 'Diesel', 'GPL', 'Hybride', 'Électrique'];

    /**
     * Rappels : intervalles d'entretien usuels, modifiables par le gérant (prix MO à 0 : il les fixe).
     *
     * @var list<array{name: string, requires_mileage: bool, reminder_km: int|null}>
     */
    private const SERVICE_TYPES = [
        ['name' => 'Vidange moteur', 'requires_mileage' => true, 'reminder_km' => 10000],
        ['name' => 'Vidange boîte', 'requires_mileage' => true, 'reminder_km' => 60000],
        ['name' => 'Kit de distribution', 'requires_mileage' => true, 'reminder_km' => 60000],
        ['name' => 'Croisillon', 'requires_mileage' => false, 'reminder_km' => null],
        ['name' => 'Plaquettes de frein', 'requires_mileage' => false, 'reminder_km' => null],
    ];

    private const ARTICLE_CATEGORIES = ['Huiles', 'Filtres', 'Distribution', 'Freinage', 'Transmission', 'Divers'];

    private const EXPENSE_CATEGORIES = ['Loyer', 'Électricité', 'Eau', 'Outillage', 'Divers'];

    /**
     * @var array<string, bool> nom => espèces
     */
    private const PAYMENT_METHODS = ['Espèces' => true, 'Chèque' => false, 'Virement' => false];

    public function run(): void
    {
        foreach (self::WILAYAS as $code => $name) {
            Wilaya::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (self::ENERGIES as $name) {
            Energy::firstOrCreate(['name' => $name]);
        }

        /** @var array<string, list<string>> $brands */
        $brands = require __DIR__.'/data/vehicle-models.php';

        foreach ($brands as $brandName => $models) {
            $brand = Brand::firstOrCreate(['name' => $brandName]);

            foreach ($models as $model) {
                VehicleModel::firstOrCreate(['brand_id' => $brand->id, 'name' => $model]);
            }
        }

        foreach (self::SERVICE_TYPES as $type) {
            ServiceType::firstOrCreate(['name' => $type['name']], $type);
        }

        foreach (self::ARTICLE_CATEGORIES as $name) {
            ArticleCategory::firstOrCreate(['name' => $name]);
        }

        foreach (SystemExpenseCategory::cases() as $key) {
            // system_key n'est pas fillable (jamais modifiable depuis l'UI) : forceCreate.
            if (! ExpenseCategory::where('system_key', $key)->exists()) {
                ExpenseCategory::forceCreate(['name' => $key->label(), 'system_key' => $key]);
            }
        }

        foreach (self::EXPENSE_CATEGORIES as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }

        foreach (self::PAYMENT_METHODS as $name => $isCash) {
            PaymentMethod::firstOrCreate(['name' => $name], ['is_cash' => $isCash]);
        }

        User::firstOrCreate(
            ['username' => config('garage.admin.username')],
            [
                'name' => config('garage.admin.name'),
                'password' => config('garage.admin.password'),
                'role' => UserRole::Gerant,
                // Mot de passe connu de tous (.env) : à remplacer dès la première connexion.
                'must_change_password' => true,
            ],
        );
    }
}
