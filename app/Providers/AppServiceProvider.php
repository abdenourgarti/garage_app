<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Article;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\SalaryPayment;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();

        // Noms stables dans les colonnes *_type (sources des mouvements de stock et des dépenses).
        Relation::enforceMorphMap([
            'purchase' => Purchase::class,
            'purchase_payment' => PurchasePayment::class,
            'service_order' => ServiceOrder::class,
            'salary_payment' => SalaryPayment::class,
        ]);

        // Tableau de bord : chiffres recalculés dès qu'un mouvement change (après le commit, sinon un autre écran remettrait l'ancien chiffre en cache).
        foreach ([Appointment::class, Payment::class, Expense::class, OtherIncome::class, Invoice::class, ServiceOrder::class, StockMovement::class, Article::class] as $model) {
            $forget = fn () => DB::afterCommit(fn () => DashboardService::forget());
            $model::saved($forget);
            $model::deleted($forget);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Une relation oubliée dans with() = une requête par ligne : erreur visible en développement et dans les tests.
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)->letters()->numbers()
            : null,
        );
    }

    /**
     * The gérant can do everything. The employé stands in for him day to day
     * (service orders, stock, appointments, clients, references) but not for
     * staff, pay or the garage settings.
     */
    protected function configureGates(): void
    {
        Gate::before(fn (User $user): ?bool => $user->isGerant() ? true : null);

        Gate::define('manage-cash', fn (User $user): bool => false);
        Gate::define('manage-employees', fn (User $user): bool => false);
        Gate::define('manage-admin', fn (User $user): bool => false);
        Gate::define('manage-references', fn (User $user): bool => true);
        Gate::define('delete-service-orders', fn (User $user): bool => false);
        Gate::define('cancel-invoices', fn (User $user): bool => false);
    }
}
