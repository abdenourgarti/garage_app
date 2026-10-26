<?php

namespace Database\Seeders;

use App\Actions\Clients\CreateClientWithVehicle;
use App\Actions\Invoices\ApplyDiscount;
use App\Actions\Invoices\CancelInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Purchases\RecordPurchase;
use App\Actions\ServiceOrders\CompleteServiceOrder;
use App\Actions\ServiceOrders\SaveServiceOrder;
use App\Actions\Stock\CreateArticle;
use App\Actions\Stock\SaveOilProduct;
use App\Actions\Vehicles\CreateVehicle;
use App\Enums\OilUsage;
use App\Enums\PurchaseStatus;
use App\Enums\StockUnit;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Energy;
use App\Models\Invoice;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Données de démonstration : `php artisan db:seed --class=DemoSeeder` (sur une base neuve pour l'historique).
 *
 * Un an de vie du garage, dans l'ordre des dates : 30 clients, 50 véhicules, 200 prestations, factures, paiements,
 * crédits, achats mensuels. Tout passe par les actions de l'application : stock, caisse et numéros sont cohérents.
 * Tirages pseudo-aléatoires à graine fixe : chaque exécution produit les mêmes données.
 * Chaque partie ne s'ajoute qu'une fois : relancer le seeder après une nouvelle phase n'ajoute que la nouvelle partie.
 */
class DemoSeeder extends Seeder
{
    private const DAYS = 365;

    private const ORDERS = 200;

    /** Stock visé après chaque réapprovisionnement mensuel. */
    private const TARGETS = [
        'Total Quartz 9000 5W-40, bidon 5 L' => 15, 'Total Quartz 9000 5W-40, bidon 4 L' => 8, 'Total Quartz 9000 5W-40, vrac' => 80,
        'Elf Evolution 900 10W-40, bidon 5 L' => 12, 'Elf Evolution 900 10W-40, vrac' => 60, 'Shell Helix HX7 10W-40, bidon 4 L' => 8,
        'Total Transmission TM 80W-90, bidon 1 L' => 12, 'Filtre à huile' => 25, 'Filtre à air' => 10, 'Filtre à gasoil' => 8,
        'Filtre d’habitacle' => 6, 'Plaquettes de frein avant' => 6, 'Liquide de frein DOT 4 (0,5 L)' => 10,
        'Kit de distribution' => 2, 'Croisillon de cardan' => 3,
    ];

    /** @var array<string, Article> */
    private array $articles = [];

    /** @var array<string, Supplier> */
    private array $suppliers = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Données de démonstration refusées en production.');

            return;
        }

        Auth::setUser(User::where('role', UserRole::Gerant)->firstOrFail());
        mt_srand(2026);

        try {
            $this->laborPrices();
            $this->catalog();
            $this->employees();
            $this->history();
        } finally {
            Carbon::setTestNow();
            mt_srand();
        }
    }

    /**
     * Fait « vivre » une opération à une date passée (dates de création, de sortie de stock, de dépense…).
     */
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

    /**
     * Fournisseurs, huiles et pièces, avec leur stock de départ, un an avant aujourd'hui.
     */
    private function catalog(): void
    {
        if (Supplier::where('name', 'Lubrifiants El Bahdja')->exists()) {
            $this->load();
            $this->command->line('Stock de démonstration déjà présent.');

            return;
        }

        $this->atDate(today()->subDays(self::DAYS + 5)->setTime(9, 0), function () {
            Supplier::create(['name' => 'Lubrifiants El Bahdja', 'phone' => '0550 21 43 65', 'address' => 'Zone industrielle, Oued Smar, Alger']);
            Supplier::create(['name' => 'Pièces Auto Mitidja', 'phone' => '0661 32 54 76', 'address' => 'Route de Blida, Boufarik']);
            Supplier::create(['name' => 'Distribution Auto Boumerdès', 'phone' => '0770 43 65 87', 'email' => 'contact@dab-auto.dz']);

            $oils = ArticleCategory::where('name', 'Huiles')->value('id');
            $save = app(SaveOilProduct::class);
            $format = fn (string $purchase, string $sale, string $alert, string $name) => ['purchase_price' => $purchase, 'sale_price' => $sale, 'alert_threshold' => $alert, 'initial_stock' => (string) self::TARGETS[$name]];

            $save->handle(null, ['brand' => 'Total', 'name' => 'Quartz 9000', 'viscosity' => '5W-40', 'usage' => OilUsage::Engine], $oils, [
                'can_5l' => $format('4200', '5200', '3', 'Total Quartz 9000 5W-40, bidon 5 L'),
                'can_4l' => $format('3500', '4400', '2', 'Total Quartz 9000 5W-40, bidon 4 L'),
                'bulk' => $format('800', '1000', '20', 'Total Quartz 9000 5W-40, vrac'),
            ]);
            $save->handle(null, ['brand' => 'Elf', 'name' => 'Evolution 900', 'viscosity' => '10W-40', 'usage' => OilUsage::Engine], $oils, [
                'can_5l' => $format('3300', '4200', '3', 'Elf Evolution 900 10W-40, bidon 5 L'),
                'bulk' => $format('650', '850', '20', 'Elf Evolution 900 10W-40, vrac'),
            ]);
            $save->handle(null, ['brand' => 'Shell', 'name' => 'Helix HX7', 'viscosity' => '10W-40', 'usage' => OilUsage::Engine], $oils, [
                'can_4l' => $format('2900', '3700', '2', 'Shell Helix HX7 10W-40, bidon 4 L'),
            ]);
            $save->handle(null, ['brand' => 'Total', 'name' => 'Transmission TM', 'viscosity' => '80W-90', 'usage' => OilUsage::Gearbox], $oils, [
                'can_1l' => $format('650', '900', '4', 'Total Transmission TM 80W-90, bidon 1 L'),
            ]);

            $category = fn (string $name) => ArticleCategory::where('name', $name)->value('id');
            $create = app(CreateArticle::class);

            foreach ([
                ['Filtres', 'Filtre à huile', 'Purflux', 'LS932', '450', '700', '4'],
                ['Filtres', 'Filtre à air', 'Mann', 'C2201', '700', '1100', '3'],
                ['Filtres', 'Filtre à gasoil', 'Bosch', 'F026402', '900', '1400', '2'],
                ['Filtres', 'Filtre d’habitacle', 'Valeo', '698870', '600', '1000', '2'],
                ['Freinage', 'Plaquettes de frein avant', 'Bosch', '0986494', '2800', '4000', '2'],
                ['Freinage', 'Liquide de frein DOT 4 (0,5 L)', 'Total', 'HBF4', '450', '700', '3'],
                ['Distribution', 'Kit de distribution', 'Gates', 'K015603XS', '9000', '12500', '1'],
                ['Transmission', 'Croisillon de cardan', 'GKN', 'U-JOINT', '1800', '2800', '1'],
            ] as [$categoryName, $name, $brand, $reference, $purchase, $sale, $alert]) {
                $create->handle([
                    'article_category_id' => $category($categoryName),
                    'name' => $name,
                    'brand' => $brand,
                    'reference' => $reference,
                    'stock_unit' => StockUnit::Unit,
                    'purchase_price' => $purchase,
                    'sale_price' => $sale,
                    'alert_threshold' => $alert,
                ], (string) self::TARGETS[$name]);
            }
        });

        $this->load();
        $this->command->info('Stock : 3 fournisseurs, 4 huiles (7 formats), 8 articles.');
    }

    private function load(): void
    {
        $this->articles = Article::all()->keyBy('name')->all();
        $this->suppliers = Supplier::all()->keyBy('name')->all();
    }

    private function article(string $name): Article
    {
        return $this->articles[$name] ?? throw new \RuntimeException("Article de démonstration introuvable : {$name}");
    }

    private function employees(): void
    {
        if (Employee::exists()) {
            return;
        }

        $this->atDate(today()->subDays(self::DAYS + 5)->setTime(8, 0), function () {
            Employee::create(['name' => 'Mourad Benali', 'position' => 'Chef mécanicien', 'phone' => '0661 10 20 30']);
            Employee::create(['name' => 'Sofiane Haddad', 'position' => 'Mécanicien', 'phone' => '0550 40 50 60']);
            Employee::create(['name' => 'Bilal Ouali', 'position' => 'Apprenti', 'phone' => '0770 70 80 90']);
        });

        $this->command->info('Employés : 3 mécaniciens.');
    }

    /**
     * Un an de prestations, jour après jour : le client et son véhicule sont créés à leur première visite,
     * le stock est réapprovisionné chaque mois, chaque facture est encaissée (ou laissée en crédit) le jour même.
     */
    private function history(): void
    {
        if (ServiceOrder::exists()) {
            $this->command->line('Prestations déjà présentes : rien d’ajouté (videz la base pour régénérer l’historique).');

            return;
        }

        $vehicles = $this->vehiclePlans();
        $visits = $this->visitDates();
        $sequence = $this->visitSequence(count($vehicles), count($visits));

        $employees = Employee::orderBy('id')->pluck('id')->all();
        $types = ServiceType::all()->keyBy('name');
        $methods = PaymentMethod::orderByDesc('is_cash')->orderBy('id')->get()->keyBy('name');
        $save = app(SaveServiceOrder::class);
        $complete = app(CompleteServiceOrder::class);
        $pay = app(RecordPayment::class);

        $created = [];
        $lastVisit = [];
        $month = null;

        foreach ($visits as $index => $date) {
            if ($date->format('Y-m') !== $month) {
                $month = $date->format('Y-m');
                $this->restock($date->setTime(8, 0), unpaid: $date->diffInDays(today()) < 31);
            }

            $plan = $vehicles[$sequence[$index]];
            $vehicle = $created[$sequence[$index]] ??= $this->atDate($date->setTime(8, 0), fn () => $this->createVehicle($plan));

            // Kilométrage : celui de l'achat du client, puis 25 à 55 km par jour entre deux visites.
            $mileage = isset($lastVisit[$vehicle->id])
                ? $lastVisit[$vehicle->id][1] + (int) $lastVisit[$vehicle->id][0]->diffInDays($date) * mt_rand(25, 55)
                : $plan['mileage'];
            $lastVisit[$vehicle->id] = [$date, $mileage];

            $kind = $this->pick(['oil_can' => 30, 'oil_bulk' => 25, 'oil_full' => 8, 'brakes' => 11, 'gearbox' => 6, 'oil_client' => 7, 'timing' => 5, 'cardan' => 4, 'oil_brakes' => 4]);
            $lines = $this->lines($kind, $types, $employees[mt_rand(0, count($employees) - 1)], $vehicle, $plan['energy']);
            $inProgress = $index >= count($visits) - 2;
            $roll = mt_rand(1, 100);

            $this->atDate($date, function () use ($save, $complete, $pay, $methods, $vehicle, $mileage, $lines, $kind, $inProgress, $roll, $index, $date) {
                $order = $save->handle(null, [
                    'vehicle_id' => $vehicle->id,
                    'date' => today()->toDateString(),
                    'mileage' => $mileage,
                    'immobilization_value' => $kind === 'timing' ? 1 : null,
                    'immobilization_unit' => $kind === 'timing' ? 'days' : 'hours',
                    'advice' => match ($kind) {
                        'oil_can', 'oil_bulk', 'oil_full' => 'Prochaine vidange dans 10 000 km.',
                        'brakes', 'oil_brakes' => 'Disques avant à surveiller.',
                        default => null,
                    },
                ], $lines);

                if ($inProgress) {
                    return;
                }

                $complete->handle($order);
                $invoice = $order->invoice()->firstOrFail();
                $method = $methods->values()[$roll <= 85 ? 0 : min($roll <= 95 ? 1 : 2, $methods->count() - 1)];

                // Une facture annulée (erreur de prix), corrigée puis refaite le jour même.
                if ($index === 120) {
                    $pay->handle($invoice, $invoice->total, now(), $method);
                    app(CancelInvoice::class)->handle($invoice, 'Erreur sur le prix de la main-d’œuvre');
                    $order->lines()->first()->update(['labor_price' => '800']);
                    $complete->handle($order->fresh());
                    $pay->handle($order->invoice()->firstOrFail(), $order->invoice()->firstOrFail()->total, now(), $method);

                    return;
                }

                if (in_array($index, [15, 70, 150], true)) {
                    $invoice = app(ApplyDiscount::class)->handle($invoice, '500');
                }

                $recent = $date->diffInDays(today()) < 20;

                match (true) {
                    // Quelques factures récentes pas encore réglées.
                    $recent && $roll % 4 === 0 => null,
                    // Paiement partiel : le reste plus tard (ou jamais, pour garder de vieux crédits).
                    $roll > 88 => $this->partial($invoice, $method, $date, settle: $roll < 97),
                    default => $pay->handle($invoice, $invoice->total, now()->addMinutes(20), $method),
                };
            });
        }

        $this->lastPurchases();

        $credit = Decimal::sub((string) Invoice::outstanding()->sum('total'), (string) Invoice::outstanding()->sum('amount_paid'));
        $this->command->info(sprintf(
            'Historique : %d clients, %d véhicules, %d prestations sur 12 mois, %d factures (dont 1 annulée), crédits clients : %s.',
            Client::count(), Vehicle::count(), ServiceOrder::count(), Invoice::count(), Decimal::money($credit),
        ));
    }

    /**
     * Acompte de 50 à 70 % le jour même ; le reste 5 à 25 jours plus tard s'il est déjà passé.
     */
    private function partial(Invoice $invoice, PaymentMethod $method, CarbonInterface $date, bool $settle): void
    {
        $pay = app(RecordPayment::class);
        $first = Decimal::mul(Decimal::div(Decimal::mul($invoice->total, (string) mt_rand(50, 70)), '10000', 0), '100');
        $pay->handle($invoice, $first, now()->addMinutes(20), $method);

        $later = $date->addDays(mt_rand(5, 25))->setTime(mt_rand(9, 17), 30);

        if ($settle && $later->isPast()) {
            $this->atDate($later, fn () => $pay->handle($invoice->fresh(), $invoice->fresh()->remaining(), now(), $method));
        }
    }

    /**
     * Début de mois : chaque fournisseur livre de quoi remonter au stock visé ; payé, sauf le dernier mois (dette fournisseur).
     */
    private function restock(CarbonInterface $date, bool $unpaid): void
    {
        $bySupplier = [];

        foreach (self::TARGETS as $name => $target) {
            $article = $this->article($name)->fresh();
            $missing = (int) ceil($target - (float) $article->stock_qty);

            if ($missing >= max(1, (int) ($target / 3))) {
                $supplier = match (true) {
                    $article->oil_product_id !== null => 'Lubrifiants El Bahdja',
                    in_array($name, ['Kit de distribution', 'Croisillon de cardan', 'Filtre à gasoil'], true) => 'Distribution Auto Boumerdès',
                    default => 'Pièces Auto Mitidja',
                };
                $bySupplier[$supplier][] = ['article_id' => $article->id, 'quantity' => (string) $missing, 'unit_price' => $article->purchase_price];
            }
        }

        $cash = PaymentMethod::where('is_cash', true)->firstOrFail();

        foreach ($bySupplier as $supplier => $lines) {
            $this->atDate($date, function () use ($supplier, $lines, $unpaid, $cash) {
                $total = Decimal::add(...array_map(fn (array $line) => Decimal::mul($line['quantity'], $line['unit_price']), $lines));

                app(RecordPurchase::class)->handle(
                    null,
                    ['supplier_id' => $this->suppliers[$supplier]->id, 'purchased_at' => today()->toDateString(), 'supplier_invoice_ref' => 'BL-'.today()->format('ymd')],
                    $lines,
                    PurchaseStatus::Received,
                    payment: ['amount' => $unpaid ? Decimal::div($total, '2', 0) : $total, 'paid_at' => today(), 'method' => $cash],
                );
            });
        }
    }

    /**
     * Une commande passée avec acompte (pas encore livrée) et un brouillon en préparation.
     */
    private function lastPurchases(): void
    {
        $record = app(RecordPurchase::class);
        $cash = PaymentMethod::where('is_cash', true)->firstOrFail();

        $this->atDate(today()->subDays(2)->setTime(10, 0), fn () => $record->handle(
            null,
            ['supplier_id' => $this->suppliers['Lubrifiants El Bahdja']->id, 'purchased_at' => today()->toDateString()],
            [['article_id' => $this->article('Shell Helix HX7 10W-40, bidon 4 L')->id, 'quantity' => '10', 'unit_price' => '2900']],
            PurchaseStatus::Ordered,
            payment: ['amount' => '10000', 'paid_at' => today(), 'method' => $cash],
        ));

        $this->atDate(now(), fn () => $record->handle(
            null,
            ['supplier_id' => $this->suppliers['Pièces Auto Mitidja']->id, 'purchased_at' => today()->toDateString()],
            [['article_id' => $this->article('Filtre d’habitacle')->id, 'quantity' => '6', 'unit_price' => '600']],
            PurchaseStatus::Draft,
        ));
    }

    /**
     * Dates des visites : 198 jours ouvrés tirés sur l'année (pas le vendredi, plusieurs visites certains jours),
     * puis 2 prestations en cours aujourd'hui.
     *
     * @return list<CarbonInterface>
     */
    private function visitDates(): array
    {
        $days = [];

        for ($day = self::DAYS; $day >= 1; $day--) {
            $date = today()->subDays($day);

            if (! $date->isFriday()) {
                $days[] = $date;
            }
        }

        $dates = [];

        for ($i = 0; $i < self::ORDERS - 2; $i++) {
            $dates[] = $days[mt_rand(0, count($days) - 1)]->setTime(mt_rand(8, 16), mt_rand(0, 3) * 15);
        }

        usort($dates, fn (CarbonInterface $a, CarbonInterface $b) => $a <=> $b);

        return [...$dates, now()->setTime(9, 0), now()->setTime(10, 30)];
    }

    /**
     * Ordre des véhicules : chacun vient au moins une fois, les autres visites vont surtout aux habitués.
     *
     * @return list<int>
     */
    private function visitSequence(int $vehicles, int $visits): array
    {
        $sequence = range(0, $vehicles - 1);
        $regulars = range(0, (int) ($vehicles * 0.6));

        while (count($sequence) < $visits) {
            $sequence[] = mt_rand(1, 3) === 1 ? mt_rand(0, $vehicles - 1) : $regulars[mt_rand(0, count($regulars) - 1)];
        }

        shuffle($sequence);

        return $sequence;
    }

    /**
     * 30 clients et 50 véhicules (certains clients en ont 2 ou 3), matricules algériens.
     *
     * @return list<array{client: array<string, mixed>, plate: string, brand: string, model: string, year: int, energy: string, mileage: int}>
     */
    private function vehiclePlans(): array
    {
        $clients = [];
        $phones = [];

        foreach (self::CLIENTS as [$firstName, $lastName, $wilaya]) {
            do {
                $phone = self::PHONE_PREFIXES[mt_rand(0, count(self::PHONE_PREFIXES) - 1)].sprintf(' %02d %02d %02d', mt_rand(0, 99), mt_rand(0, 99), mt_rand(0, 99));
            } while (isset($phones[$phone]));

            $phones[$phone] = true;
            $clients[] = ['first_name' => $firstName ?: null, 'last_name' => $lastName, 'phone' => $phone, 'wilaya' => $wilaya];
        }

        // Les 30 premiers véhicules à chacun des clients, les 20 suivants à des clients tirés au sort.
        $owners = [...array_keys($clients), ...array_map(fn () => mt_rand(0, count($clients) - 1), range(1, 20))];
        $plates = [];
        $plans = [];

        foreach ($owners as $owner) {
            [$brand, $model, $energy, $utility] = self::CARS[mt_rand(0, count(self::CARS) - 1)];
            $year = mt_rand(2006, 2024);

            do {
                $plate = sprintf('%05d-%d%02d-%s', mt_rand(1, 99999), $utility ? 3 : 1, $year % 100, $clients[$owner]['wilaya']);
            } while (isset($plates[$plate]));

            $plates[$plate] = true;
            $plans[] = [
                'client' => $clients[$owner] + ['key' => $owner],
                'plate' => $plate,
                'brand' => $brand,
                'model' => $model,
                'year' => $year,
                'energy' => $energy,
                'mileage' => min(320000, (2026 - $year) * mt_rand(11000, 19000) + mt_rand(1000, 9000)),
            ];
        }

        return $plans;
    }

    /**
     * @param  array{client: array<string, mixed>, plate: string, brand: string, model: string, year: int, energy: string, mileage: int}  $plan
     */
    private function createVehicle(array $plan): Vehicle
    {
        $brandId = Brand::where('name', $plan['brand'])->value('id');
        $vehicle = [
            'plate' => $plan['plate'],
            'brand_id' => $brandId,
            'vehicle_model_id' => VehicleModel::where('brand_id', $brandId)->where('name', $plan['model'])->value('id'),
            'year' => $plan['year'],
            'energy_id' => Energy::where('name', $plan['energy'])->value('id'),
            'last_mileage' => $plan['mileage'],
        ];

        $client = Client::where('phone_normalized', str_replace(' ', '', $plan['client']['phone']))->first();

        if ($client) {
            return app(CreateVehicle::class)->handle($client, $vehicle);
        }

        [, $created] = app(CreateClientWithVehicle::class)->handle([
            'first_name' => $plan['client']['first_name'],
            'last_name' => $plan['client']['last_name'],
            'phone' => $plan['client']['phone'],
            'wilaya_id' => Wilaya::where('code', $plan['client']['wilaya'])->value('id'),
        ], $vehicle);

        return $created;
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function pick(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    /**
     * @param  Collection<string, ServiceType>  $types
     * @return list<array<string, mixed>>
     */
    private function lines(string $kind, $types, int $employee, Vehicle $vehicle, string $energy): array
    {
        $stock = fn (string $name, string $quantity = '1') => ['origin' => 'stock', 'article_id' => $this->article($name)->id, 'quantity' => $quantity, 'unit_price' => $this->article($name)->sale_price];
        $line = fn (string $name, array $parts) => ['service_type_id' => $types[$name]->id, 'employee_id' => $employee, 'labor_price' => $types[$name]->default_labor_price, 'parts' => $parts];
        $total = $vehicle->id % 2 === 0;
        $can = $total ? 'Total Quartz 9000 5W-40, bidon 5 L' : 'Elf Evolution 900 10W-40, bidon 5 L';
        $bulk = $total ? 'Total Quartz 9000 5W-40, vrac' : 'Elf Evolution 900 10W-40, vrac';
        $litres = ['4', '4.5', '5'][$vehicle->id % 3];
        $oilChange = [$stock($can), $stock('Filtre à huile')];

        return match ($kind) {
            'oil_can' => [$line('Vidange moteur', $oilChange)],
            'oil_bulk' => [$line('Vidange moteur', [$stock($bulk, $litres), $stock('Filtre à huile')])],
            'oil_full' => [$line('Vidange moteur', [
                $stock($vehicle->id % 3 === 0 ? 'Total Quartz 9000 5W-40, bidon 4 L' : 'Shell Helix HX7 10W-40, bidon 4 L'),
                $stock('Filtre à huile'),
                $stock('Filtre à air'),
                ...($energy === 'Diesel' ? [$stock('Filtre à gasoil')] : [$stock('Filtre d’habitacle')]),
            ])],
            'oil_client' => [$line('Vidange moteur', [
                ['origin' => 'client', 'designation' => 'Huile 5W-30, bidon 5 L', 'quantity' => '1'],
                $stock('Filtre à huile'),
            ])],
            'brakes' => [$line('Plaquettes de frein', [$stock('Plaquettes de frein avant'), $stock('Liquide de frein DOT 4 (0,5 L)')])],
            'oil_brakes' => [$line('Vidange moteur', $oilChange), $line('Plaquettes de frein', [$stock('Plaquettes de frein avant')])],
            'gearbox' => [$line('Vidange boîte', [$stock('Total Transmission TM 80W-90, bidon 1 L', '2')])],
            'timing' => [$line('Kit de distribution', [
                $stock('Kit de distribution'),
                ['origin' => 'external', 'designation' => 'Pompe à eau', 'quantity' => '1', 'unit_cost' => '4500', 'unit_price' => '5500'],
            ])],
            'cardan' => [$line('Croisillon', [$stock('Croisillon de cardan')])],
        };
    }

    private const PHONE_PREFIXES = ['0550', '0551', '0555', '0560', '0661', '0662', '0665', '0670', '0770', '0771', '0772', '0773', '0790', '0796'];

    /**
     * Marque, modèle, énergie, utilitaire.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: bool}>
     */
    private const CARS = [
        ['Renault', 'Clio 4', 'Diesel', false], ['Renault', 'Clio 4', 'Essence', false], ['Renault', 'Symbol', 'Essence', false],
        ['Renault', 'Kangoo', 'Diesel', true], ['Dacia', 'Logan', 'Diesel', false], ['Dacia', 'Duster', 'Diesel', false],
        ['Dacia', 'Sandero', 'Essence', false], ['Peugeot', '208', 'Essence', false], ['Peugeot', '301', 'Diesel', false],
        ['Peugeot', '308', 'Diesel', false], ['Peugeot', 'Partner', 'Diesel', true], ['Hyundai', 'i10', 'Essence', false],
        ['Hyundai', 'Grand i10', 'Essence', false], ['Hyundai', 'Accent', 'Essence', false], ['Hyundai', 'Tucson', 'Diesel', false],
        ['Hyundai', 'H-1', 'Diesel', true], ['Kia', 'Picanto', 'Essence', false], ['Kia', 'Rio', 'Essence', false],
        ['Kia', 'Sportage', 'Diesel', false], ['Volkswagen', 'Golf 7', 'Diesel', false], ['Volkswagen', 'Polo', 'Essence', false],
        ['Toyota', 'Hilux', 'Diesel', true], ['Toyota', 'Yaris', 'Essence', false], ['Chevrolet', 'Sail', 'Essence', false],
        ['Chevrolet', 'Aveo', 'Essence', false], ['Suzuki', 'Swift', 'Essence', false], ['Suzuki', 'Alto', 'Essence', false],
        ['Fiat', 'Tipo', 'Diesel', false], ['Seat', 'Ibiza', 'Essence', false],
    ];

    /**
     * Prénom (vide pour une entreprise), nom, code de wilaya.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const CLIENTS = [
        ['Karim', 'Benmoussa', '16'], ['Nadia', 'Cherif', '16'], ['Yacine', 'Mansouri', '09'], ['Samir', 'Boudiaf', '35'],
        ['Amel', 'Hamidi', '16'], ['Rachid', 'Kaci', '15'], ['Lamia', 'Saadi', '42'], ['Mohamed', 'Belkacem', '16'],
        ['Sonia', 'Ait Ahmed', '15'], ['Hocine', 'Ziani', '09'], ['Fatiha', 'Bouzid', '16'], ['Abdelkader', 'Rahmani', '35'],
        ['', 'Transport Mazouz', '16'], ['Walid', 'Ferhat', '42'], ['Djamel', 'Haddad', '16'], ['Meriem', 'Belhadj', '09'],
        ['Omar', 'Chibane', '16'], ['Souad', 'Larbi', '35'], ['Nabil', 'Taleb', '16'], ['Khaled', 'Messaoudi', '09'],
        ['Imane', 'Bensalem', '16'], ['Reda', 'Guerfi', '06'], ['Farid', 'Amrani', '16'], ['Lynda', 'Ouali', '15'],
        ['Mehdi', 'Bouchama', '16'], ['Hakim', 'Slimani', '42'], ['Assia', 'Kherbache', '09'], ['', 'SARL Atlas Livraison', '16'],
        ['Mourad', 'Zerrouki', '10'], ['Salim', 'Benyahia', '16'],
    ];
}
