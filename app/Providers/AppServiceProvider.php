<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
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
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)->letters()->numbers()
            : null,
        );
    }

    /**
     * The gérant can do everything; these gates list what the employé cannot.
     */
    protected function configureGates(): void
    {
        Gate::before(fn (User $user): ?bool => $user->isGerant() ? true : null);

        Gate::define('manage-cash', fn (User $user): bool => false);
        Gate::define('manage-employees', fn (User $user): bool => false);
        Gate::define('manage-admin', fn (User $user): bool => false);
    }
}
