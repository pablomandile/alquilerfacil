<?php

namespace App\Providers;

use App\Listeners\VincularOwnerAlIngresar;
use App\Models\User;
use App\Services\Indices\BcraIclSource;
use App\Services\Indices\IndecIpcSource;
use App\Services\Indices\SincronizadorDeIndices;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        // Las fuentes se listan acá para que agregar un índice nuevo sea sumar una
        // clase y una línea, sin tocar el sincronizador ni el comando.
        $this->app->singleton(SincronizadorDeIndices::class, fn ($app) => new SincronizadorDeIndices([
            $app->make(IndecIpcSource::class),
            $app->make(BcraIclSource::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // El admin puede todo; el resto pasa por las policies de cada modelo.
        Gate::before(fn (User $user) => $user->esAdmin() ? true : null);

        Event::listen(Login::class, VincularOwnerAlIngresar::class);
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
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
