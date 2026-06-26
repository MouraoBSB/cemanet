<?php

namespace App\Providers;

use App\Importacao\LeitorBlog;
use App\Importacao\LeitorBlogMysql;
use App\Importacao\LeitorLegado;
use App\Importacao\LeitorLegadoMysql;
use App\Support\Blog\FonteReflexao;
use App\Support\Blog\ReflexaoConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LeitorLegado::class, LeitorLegadoMysql::class);
        $this->app->bind(LeitorBlog::class, LeitorBlogMysql::class);
        $this->app->bind(FonteReflexao::class, ReflexaoConfig::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('pt_BR');
    }
}
