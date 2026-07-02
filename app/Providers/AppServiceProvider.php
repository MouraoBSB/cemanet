<?php

namespace App\Providers;

use App\Importacao\LeitorAgenda;
use App\Importacao\LeitorAgendaMysql;
use App\Importacao\LeitorBlog;
use App\Importacao\LeitorBlogMysql;
use App\Importacao\LeitorLegado;
use App\Importacao\LeitorLegadoMysql;
use App\Listeners\CalcularHashMidia;
use App\Listeners\CaparOriginalDaMidia;
use App\Support\Blog\FonteReflexao;
use App\Support\Blog\ReflexaoConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
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
    }
}
