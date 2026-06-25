<?php

namespace App\Providers;

use App\Importacao\LeitorLegado;
use App\Importacao\LeitorLegadoMysql;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LeitorLegado::class, LeitorLegadoMysql::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
