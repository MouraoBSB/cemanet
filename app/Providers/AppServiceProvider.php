<?php

namespace App\Providers;

use App\Auth\HasherLegadoCema;
use App\Importacao\LeitorAgenda;
use App\Importacao\LeitorAgendaMysql;
use App\Importacao\LeitorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituaisMysql;
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
use App\Models\User;
use App\Support\Autorizacao\AcessoPorTipo;
use App\Support\Blog\FonteReflexao;
use App\Support\Blog\ReflexaoConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        $this->app->bind(LeitorAutoresEspirituais::class, LeitorAutoresEspirituaisMysql::class);
        $this->app->bind(FonteReflexao::class, ReflexaoConfig::class);

        // SCOPED, nunca singleton: o worker (queue:work) não reconstrói o container entre jobs —
        // só chama forgetScopedInstances (QueueServiceProvider:263), que preserva singletons. Um
        // memo de config de ACESSO em singleton viraria cache persistente dentro do worker.
        $this->app->scoped(AcessoPorTipo::class, fn (): AcessoPorTipo => new AcessoPorTipo);
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

        // Portão do admin: administrador passa em qualquer ability; os demais caem nas policies.
        // (register_permission_check_method está OFF, então este é o único Gate::before do sistema.)
        Gate::before(fn (User $usuario) => $usuario->hasRole('administrador') ? true : null);
    }
}
