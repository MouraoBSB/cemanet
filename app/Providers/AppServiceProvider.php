<?php

namespace App\Providers;

use App\Auth\HasherLegadoCema;
use App\Importacao\LeitorAgenda;
use App\Importacao\LeitorAgendaMysql;
use App\Importacao\LeitorBlog;
use App\Importacao\LeitorBlogMysql;
use App\Importacao\LeitorEventos;
use App\Importacao\LeitorEventosMysql;
use App\Importacao\LeitorLegado;
use App\Importacao\LeitorLegadoMysql;
use App\Importacao\LeitorUsuarios;
use App\Importacao\LeitorUsuariosMysql;
use App\Listeners\CalcularHashMidia;
use App\Listeners\CaparOriginalDaMidia;
use App\Support\Blog\FonteReflexao;
use App\Support\Blog\ReflexaoConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LeitorLegado::class, LeitorLegadoMysql::class);
        $this->app->bind(LeitorBlog::class, LeitorBlogMysql::class);
        $this->app->bind(LeitorAgenda::class, LeitorAgendaMysql::class);
        $this->app->bind(LeitorEventos::class, LeitorEventosMysql::class);
        $this->app->bind(LeitorUsuarios::class, LeitorUsuariosMysql::class);
        $this->app->bind(FonteReflexao::class, ReflexaoConfig::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('pt_BR');

        Event::listen(MediaHasBeenAddedEvent::class, CaparOriginalDaMidia::class);
        Event::listen(MediaHasBeenAddedEvent::class, CalcularHashMidia::class); // DEPOIS do cap → hash pós-cap

        Hash::extend('cema', function ($app) {
            return new HasherLegadoCema($app['config']['hashing.bcrypt'] ?? []);
        });
    }
}
