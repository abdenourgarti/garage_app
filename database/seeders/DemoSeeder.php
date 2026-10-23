<?php

namespace Database\Seeders;

use App\Actions\Clients\CreateClientWithVehicle;
use App\Actions\Invoices\ApplyDiscount;
use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\GenerateInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Purchases\RecordPurchase;
use App\Actions\ServiceOrders\CompleteServiceOrder;
use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Actions\Stock\CreateArticle;
use App\Actions\Stock\SaveOilProduct;
use App\Actions\Vehicles\CreateVehicle;
use App\Enums\OilUsage;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceOrderStatus;
use App\Enums\StockUnit;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Energy;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\ServiceOrder;
use App\Models\ServiceType;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\Wilaya;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Données de démonstration : `php artisan db:seed --class=DemoSeeder`.
 *
 * Tout passe par les actions de l'application (stock, achats, prestations) : les chiffres sont cohérents.
 * Chaque partie ne s'ajoute qu'une fois ; relancer le seeder après une nouvelle phase n'ajoute que la nouvelle partie.
 */
class DemoSeeder extends Seeder
{
    /** @var array<string, Article> */
    private array $articles = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Données de démonstration refusées en production.');

            return;
        }

        Auth::setUser(User::where('role', UserRole::Gerant)->firstOrFail());

        try {
            $this->laborPrices();
            $this->stock();
            $this->purchases();
            $this->clients();
            $this->employees();
            $this->serviceOrders();
            $this->invoices();
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Fait « vivre » une opération à une date passée (dates de création, de sortie de stock, de dépense…).
     */
    private function at(int $daysAgo, int $hour, callable $callback): mixed
    {
        return $this->atDate(today()->subDays($daysAgo)->setTime($hour, 15), $callback);
    }

    private function atDate(CarbonInterface $date, callable $callback): mixed
    {
        Carbon::setTestNow($date);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Prix de main-d'œuvre proposés, seulement là où le gérant n'en a pas encore mis.
     */
    private function laborPrices(): void
    {
        $prices = ['Vidange moteur' => 1000, 'Vidange boîte' => 800, 'Kit de distribution' => 8000, 'Croisillon' => 2500, 'Plaquettes de frein' => 1500];

        foreach ($prices as $name => $price) {
            ServiceType::where('name', $name)->where('default_labor_price', 0)->update(['default_labor_price' => $price]);
        }
    }

    private function stock(): void
    {
        if (Supplier::where('name', 'Lubrifiants El Bahdja')->exists()) {
            $this->loadArticles();
            $this->command->line('Stock de démonstration déjà présent.');

            return;
        }

        $this->at(95, 9, function () {
            Supplier::create(['name' => 'Lubrifiants El Bahdja', 'phone' => '0550 21 43 65', 'address' => 'Zone industrielle, Oued Smar, Alger']);
            Supplier::create(['name' => 'Pièces Auto Mitidja', 'phone' => '0661 32 54 76', 'address' => 'Route de Blida, Boufarik']);
            Supplier::create(['name' => 'Distribution Auto Boumerdès', 'phone' => '0770 43 65 87', 'email' => 'contact@dab-auto.dz']);

            $oils = ArticleCategory::where('name', 'Huiles')->value('id');
            $save = app(SaveOilProduct::class);

            $save->handle(null, ['brand' => 'Total', 'name' => 'Quartz 9000', 'viscosity' => '5W-40', 'usage' => OilUsage::Engine], $oils, [
                'can_5l' => ['purchase_price' => '4200', 'sale_price' => '5200', 'alert_threshold' => '3', 'initial_stock' => '6'],
                'can_4l' => ['purchase_price' => '3500', 'sale_price' => '4400', 'alert_threshold' => '2', 'initial_stock' => '4'],
                'bulk' => ['purchase_price' => '800', 'sale_price' => '1000', 'alert_threshold' => '20', 'initial_stock' => '30'],
            ]);
            $save->handle(null, ['brand' => 'Elf', 'name' => 'Evolution 900', 'viscosity' => '10W-40', 'usage' => OilUsage::Engine], $oils, [
                'can_5l' => ['purchase_price' => '3300', 'sale_price' => '4200', 'alert_threshold' => '3', 'initial_stock' => '8'],
                'bulk' => ['purchase_price' => '650', 'sale_price' => '850', 'alert_threshold' => '20', 'initial_stock' => '40'],
            ]);
            $save->handle(null, ['brand' => 'Shell', 'name' => 'Helix HX7', 'viscosity' => '10W-40', 'usage' => OilUsage::Engine], $oils, [
                'can_4l' => ['purchase_price' => '2900', 'sale_price' => '3700', 'alert_threshold' => '2', 'initial_stock' => '5'],
            ]);
            $save->handle(null, ['brand' => 'Total', 'name' => 'Transmission TM', 'viscosity' => '80W-90', 'usage' => OilUsage::Gearbox], $oils, [
                'can_1l' => ['purchase_price' => '650', 'sale_price' => '900', 'alert_threshold' => '4', 'initial_stock' => '12'],
            ]);

            $category = fn (string $name) => ArticleCategory::where('name', $name)->value('id');
            $create = app(CreateArticle::class);

            foreach ([
                ['Filtres', 'Filtre à huile', 'Purflux', 'LS932', '450', '700', '4', '8'],
                ['Filtres', 'Filtre à air', 'Mann', 'C2201', '700', '1100', '3', '4'],
                ['Filtres', 'Filtre à gasoil', 'Bosch', 'F026402', '900', '1400', '2', '5'],
                ['Filtres', 'Filtre d’habitacle', 'Valeo', '698870', '600', '1000', '2', '6'],
                ['Freinage', 'Plaquettes de frein avant', 'Bosch', '0986494', '2800', '4000', '2', '3'],
                ['Freinage', 'Liquide de frein DOT 4 (0,5 L)', 'Total', 'HBF4', '450', '700', '3', '10'],
                ['Distribution', 'Kit de distribution', 'Gates', 'K015603XS', '9000', '12500', '1', '1'],
                ['Transmission', 'Croisillon de cardan', 'GKN', 'U-JOINT', '1800', '2800', '1', '1'],
            ] as [$categoryName, $name, $brand, $reference, $purchase, $sale, $alert, $initial]) {
                $create->handle([
                    'article_category_id' => $category($categoryName),
                    'name' => $name,
                    'brand' => $brand,
                    'reference' => $reference,
                    'stock_unit' => StockUnit::Unit,
                    'purchase_price' => $purchase,
                    'sale_price' => $sale,
                    'alert_threshold' => $alert,
                ], $initial);
            }
        });

        $this->loadArticles();
        $this->command->info('Stock : 3 fournisseurs, 4 huiles (7 formats), 8 articles.');
    }

    private function loadArticles(): void
    {
        $this->articles = Article::all()->keyBy('name')->all();
    }

    private function article(string $name): Article
    {
        return $this->articles[$name] ?? throw new \RuntimeException("Article de démonstration introuvable : {$name}");
    }

    private function purchases(): void
    {
        $bahdja = Supplier::where('name', 'Lubrifiants El Bahdja')->firstOrFail();

        if ($bahdja->purchases()->exists()) {
            return;
        }

        $mitidja = Supplier::where('name', 'Pièces Auto Mitidja')->firstOrFail();
        $boumerdes = Supplier::where('name', 'Distribution Auto Boumerdès')->firstOrFail();
        $method = fn (string $name) => PaymentMethod::where('name', $name)->firstOrFail();
        $record = app(RecordPurchase::class);

        $plans = [
            // [jours, fournisseur, lignes, statut, paiement (montant, mode) ou null, référence]
            [60, $bahdja, [['Total Quartz 9000 5W-40, bidon 5 L', 10, 4200], ['Total Quartz 9000 5W-40, vrac', 60, 800]], PurchaseStatus::Received, ['90000', 'Espèces'], 'BL-2231'],
            [45, $mitidja, [['Filtre à huile', 20, 450], ['Filtre à air', 10, 700], ['Plaquettes de frein avant', 6, 2800]], PurchaseStatus::Received, ['15000', 'Chèque'], 'F-0457'],
            [30, $bahdja, [['Elf Evolution 900 10W-40, bidon 5 L', 8, 3300], ['Total Transmission TM 80W-90, bidon 1 L', 12, 650]], PurchaseStatus::Received, ['34200', 'Espèces'], 'BL-2302'],
            [12, $boumerdes, [['Kit de distribution', 2, 9000], ['Croisillon de cardan', 3, 1800], ['Filtre à gasoil', 6, 900]], PurchaseStatus::Received, ['28800', 'Virement'], 'FA-118'],
            [2, $bahdja, [['Shell Helix HX7 10W-40, bidon 4 L', 10, 2900], ['Elf Evolution 900 10W-40, vrac', 60, 650]], PurchaseStatus::Ordered, ['20000', 'Espèces'], null],
            [0, $mitidja, [['Filtre à huile', 20, 450], ['Filtre d’habitacle', 6, 600]], PurchaseStatus::Draft, null, null],
        ];

        foreach ($plans as [$days, $supplier, $lines, $status, $payment, $reference]) {
            $this->at($days, 10, fn () => $record->handle(
                null,
                ['supplier_id' => $supplier->id, 'purchased_at' => today()->toDateString(), 'supplier_invoice_ref' => $reference],
                array_map(fn (array $line) => ['article_id' => $this->article($line[0])->id, 'quantity' => (string) $line[1], 'unit_price' => (string) $line[2]], $lines),
                $status,
                payment: $payment ? ['amount' => $payment[0], 'paid_at' => today(), 'method' => $method($payment[1])] : null,
            ));
        }

        $this->loadArticles();
        $this->command->info('Achats : 4 reçus (dont 1 avec reste dû), 1 commandé avec acompte, 1 brouillon.');
    }

    private function clients(): void
    {
        if (Client::where('phone_normalized', '0550123456')->exists()) {
            $this->command->line('Clients de démonstration déjà présents.');

            return;
        }

        $create = app(CreateClientWithVehicle::class);
        $createVehicle = app(CreateVehicle::class);

        foreach (self::CLIENTS as $index => [$firstName, $lastName, $phone, $wilaya, $vehicles]) {
            $this->at(100 - $index, 9, function () use ($create, $createVehicle, $firstName, $lastName, $phone, $wilaya, $vehicles) {
                [$client] = $create->handle([
                    'first_name' => $firstName ?: null,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'wilaya_id' => Wilaya::where('code', $wilaya)->value('id'),
                ], $this->vehicleData($vehicles[0]));

                foreach (array_slice($vehicles, 1) as $vehicle) {
                    $createVehicle->handle($client, $this->vehicleData($vehicle));
                }
            });
        }

        $this->command->info('Clients : '.count(self::CLIENTS).' clients et leurs véhicules.');
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: int, 4: string, 5: int}  $vehicle  plaque, marque, modèle, année, énergie, km
     * @return array<string, mixed>
     */
    private function vehicleData(array $vehicle): array
    {
        [$plate, $brand, $model, $year, $energy, $mileage] = $vehicle;
        $brandId = Brand::where('name', $brand)->value('id');

        return [
            'plate' => $plate,
            'brand_id' => $brandId,
            'vehicle_model_id' => VehicleModel::where('brand_id', $brandId)->where('name', $model)->value('id'),
            'year' => $year,
            'energy_id' => Energy::where('name', $energy)->value('id'),
            'last_mileage' => $mileage,
        ];
    }

    private function employees(): void
    {
        if (Employee::exists()) {
            return;
        }

        $this->at(100, 8, function () {
            Employee::create(['name' => 'Mourad Benali', 'position' => 'Chef mécanicien', 'phone' => '0661 10 20 30']);
            Employee::create(['name' => 'Sofiane Haddad', 'position' => 'Mécanicien', 'phone' => '0550 40 50 60']);
            Employee::create(['name' => 'Bilal Ouali', 'position' => 'Apprenti', 'phone' => '0770 70 80 90']);
        });

        $this->command->info('Employés : 3 mécaniciens.');
    }

    private function serviceOrders(): void
    {
        if (ServiceOrder::exists()) {
            $this->command->line('Prestations déjà présentes : rien d’ajouté.');

            return;
        }

        $vehicles = Vehicle::whereIn('plate_normalized', array_map(
            fn (array $vehicle) => preg_replace('/[^A-Z0-9]/', '', strtoupper($vehicle[0])),
            array_merge(...array_column(self::CLIENTS, 4)),
        ))->orderBy('id')->get()->values();
        $employees = Employee::orderBy('id')->pluck('id')->all();
        $type = fn (string $name) => ServiceType::where('name', $name)->firstOrFail();
        $save = app(SaveServiceOrder::class);
        $complete = app(CompleteServiceOrder::class);
        $kinds = ['oil_can', 'oil_bulk', 'brakes', 'oil_full', 'gearbox', 'oil_client', 'timing', 'cardan', 'oil_can', 'oil_bulk'];
        $mileage = $vehicles->mapWithKeys(fn (Vehicle $vehicle) => [$vehicle->id => (int) $vehicle->last_mileage])->all();
        $total = 26;

        for ($i = 0; $i < $total; $i++) {
            $vehicle = $vehicles[($i * 7) % $vehicles->count()];
            $mileage[$vehicle->id] += $i < $vehicles->count() ? 0 : 7000 + ($i * 37) % 2500;
            $employee = $employees[$i % count($employees)];
            $kind = $kinds[$i % count($kinds)];
            $lines = $this->lines($kind, $type, $employee, $vehicle);
            $inProgress = $i >= $total - 2;

            $this->at($inProgress ? 0 : 88 - (int) round($i * 3.5), 9 + $i % 8, function () use ($save, $complete, $vehicle, $mileage, $lines, $inProgress, $kind) {
                $order = $save->handle(null, [
                    'vehicle_id' => $vehicle->id,
                    'date' => today()->toDateString(),
                    'mileage' => $mileage[$vehicle->id],
                    'immobilization_value' => $kind === 'timing' ? 1 : null,
                    'immobilization_unit' => $kind === 'timing' ? 'days' : 'hours',
                    'advice' => match ($kind) {
                        'oil_can', 'oil_bulk' => 'Prochaine vidange dans 10 000 km.',
                        'brakes' => 'Disques avant à surveiller.',
                        default => null,
                    },
                ], $lines);

                if (! $inProgress) {
                    $complete->handle($order);
                }
            });
        }

        $this->command->info("Prestations : {$total} (dont 2 en cours aujourd’hui).");
    }

    /**
     * Factures des prestations terminées (créées avant la phase 6 : rattrapées ici), puis encaissements :
     * la plupart payées le jour même, quelques crédits, une remise, une facture annulée puis refaite.
     */
    private function invoices(): void
    {
        if (Payment::exists()) {
            $this->command->line('Paiements déjà présents : rien d’ajouté.');

            return;
        }

        $generate = app(GenerateInvoice::class);
        $orders = ServiceOrder::where('status', ServiceOrderStatus::Completed)->orderBy('completed_at')->get();

        foreach ($orders->filter(fn (ServiceOrder $order) => ! $order->invoice()->exists()) as $order) {
            $this->atDate($order->completed_at, fn () => $generate->handle($order));
        }

        $pay = app(RecordPayment::class);
        $methods = PaymentMethod::orderByDesc('is_cash')->orderBy('id')->get()->values();
        $count = $orders->count();

        foreach ($orders->values() as $index => $order) {
            $invoice = $order->invoice()->firstOrFail();
            $method = $index % 7 === 3 ? $methods[min(1, $methods->count() - 1)] : $methods[0];
            $paidAt = $order->completed_at->addMinutes(20);

            if ($index === 4) {
                // Facture annulée (erreur de prix), prestation corrigée et refacturée le jour même.
                $this->atDate($paidAt, function () use ($order, $invoice, $pay, $method) {
                    $pay->handle($invoice, $invoice->total, now(), $method);
                    app(CancelInvoice::class)->handle($invoice, 'Erreur sur le prix de la main-d’œuvre');
                    $order->lines()->first()->update(['labor_price' => '800']);
                    app(CompleteServiceOrder::class)->handle($order->fresh());
                    $pay->handle($order->invoice()->firstOrFail(), $order->invoice()->firstOrFail()->total, now(), $method);
                });

                continue;
            }

            $this->atDate($paidAt, function () use ($index, $count, $invoice, $pay, $method) {
                if ($index === 2) {
                    app(ApplyDiscount::class)->handle($invoice, '500');
                    $invoice->refresh();
                }

                match (true) {
                    $index >= $count - 2 => null, // les deux dernières : pas encore payées
                    $index % 6 === 5 => $pay->handle($invoice, Decimal::div(Decimal::mul($invoice->total, '6'), '10', 0), now(), $method),
                    default => $pay->handle($invoice, $invoice->total, now(), $method),
                };
            });
        }

        $credit = Decimal::sub((string) Invoice::outstanding()->sum('total'), (string) Invoice::outstanding()->sum('amount_paid'));
        $this->command->info('Factures : '.Invoice::count().' (dont 1 annulée), crédits clients : '.Decimal::money($credit).'.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lines(string $kind, callable $type, int $employee, Vehicle $vehicle): array
    {
        $stock = fn (string $name, string $quantity = '1') => ['origin' => 'stock', 'article_id' => $this->article($name)->id, 'quantity' => $quantity, 'unit_price' => $this->article($name)->sale_price];
        $line = fn (string $name, array $parts) => ['service_type_id' => $type($name)->id, 'employee_id' => $employee, 'labor_price' => $type($name)->default_labor_price, 'parts' => $parts];
        $can = $vehicle->id % 2 === 0 ? 'Total Quartz 9000 5W-40, bidon 5 L' : 'Elf Evolution 900 10W-40, bidon 5 L';
        $bulk = $vehicle->id % 2 === 0 ? 'Total Quartz 9000 5W-40, vrac' : 'Elf Evolution 900 10W-40, vrac';

        return match ($kind) {
            'oil_can' => [$line('Vidange moteur', [$stock($can), $stock('Filtre à huile')])],
            'oil_bulk' => [$line('Vidange moteur', [$stock($bulk, '4.5'), $stock('Filtre à huile')])],
            'oil_full' => [$line('Vidange moteur', [$stock('Shell Helix HX7 10W-40, bidon 4 L'), $stock('Filtre à huile'), $stock('Filtre à air'), $stock('Filtre à gasoil')])],
            'oil_client' => [$line('Vidange moteur', [
                ['origin' => 'client', 'designation' => 'Huile 5W-30, bidon 5 L', 'quantity' => '1'],
                $stock('Filtre à huile'),
            ])],
            'brakes' => [$line('Plaquettes de frein', [$stock('Plaquettes de frein avant'), $stock('Liquide de frein DOT 4 (0,5 L)')])],
            'gearbox' => [$line('Vidange boîte', [$stock('Total Transmission TM 80W-90, bidon 1 L', '2')])],
            'timing' => [$line('Kit de distribution', [
                $stock('Kit de distribution'),
                ['origin' => 'external', 'designation' => 'Pompe à eau', 'quantity' => '1', 'unit_cost' => '4500', 'unit_price' => '5500'],
            ])],
            'cardan' => [$line('Croisillon', [$stock('Croisillon de cardan')])],
        };
    }

    /**
     * Prénom, nom, téléphone, wilaya, véhicules [plaque, marque, modèle, année, énergie, km].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: list<array{0: string, 1: string, 2: string, 3: int, 4: string, 5: int}>}>
     */
    private const CLIENTS = [
        ['Karim', 'Benmoussa', '0550 12 34 56', '16', [['01234-114-16', 'Renault', 'Clio 4', 2014, 'Diesel', 142000]]],
        ['Nadia', 'Cherif', '0661 23 45 67', '16', [['04521-119-16', 'Hyundai', 'Grand i10', 2019, 'Essence', 61000]]],
        ['Yacine', 'Mansouri', '0770 34 56 78', '09', [['13579-115-09', 'Dacia', 'Logan', 2015, 'Diesel', 188000], ['24680-120-09', 'Dacia', 'Duster', 2020, 'Diesel', 74000]]],
        ['Samir', 'Boudiaf', '0555 45 67 89', '35', [['00987-212-35', 'Peugeot', '208', 2012, 'Essence', 155000]]],
        ['Amel', 'Hamidi', '0662 56 78 90', '16', [['11223-117-16', 'Volkswagen', 'Golf 7', 2017, 'Diesel', 98000]]],
        ['Rachid', 'Kaci', '0771 67 89 01', '15', [['33445-110-15', 'Toyota', 'Hilux', 2010, 'Diesel', 265000]]],
        ['Lamia', 'Saadi', '0556 78 90 12', '42', [['55667-118-42', 'Kia', 'Picanto', 2018, 'Essence', 52000]]],
        ['Mohamed', 'Belkacem', '0663 89 01 23', '16', [['77889-113-16', 'Renault', 'Symbol', 2013, 'Essence', 171000]]],
        ['Sonia', 'Ait Ahmed', '0772 90 12 34', '15', [['99001-121-15', 'Peugeot', '301', 2021, 'Diesel', 43000]]],
        ['Hocine', 'Ziani', '0557 01 23 45', '09', [['12121-116-09', 'Suzuki', 'Swift', 2016, 'Essence', 89000]]],
        ['Fatiha', 'Bouzid', '0664 12 34 50', '16', [['34343-111-16', 'Chevrolet', 'Sail', 2011, 'Essence', 203000]]],
        ['Abdelkader', 'Rahmani', '0773 23 45 61', '35', [['56565-122-35', 'Fiat', 'Tipo', 2022, 'Diesel', 31000], ['78787-208-35', 'Renault', 'Kangoo', 2008, 'Diesel', 290000]]],
        ['', 'Transport Mazouz', '0558 34 56 72', '16', [['90909-319-16', 'Hyundai', 'H-1', 2019, 'Diesel', 132000]]],
        ['Walid', 'Ferhat', '0665 45 67 83', '42', [['21212-115-42', 'Seat', 'Ibiza', 2015, 'Essence', 117000]]],
    ];
}
