<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\TemIniciais;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'origem_legado_id', 'socio', 'ativo', 'email_verified_at', 'google_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TemIniciais;

    public function canAccessPanel(Panel $panel): bool
    {
        // Painel /admin é exclusivo de administrador. É o ÚNICO portão do painel (não há rota,
        // policy ou middleware que gateie por papel além deste). Diretores NÃO acessam o /admin.
        return $this->hasRole('administrador');
    }

    public function perfil(): HasOne
    {
        return $this->hasOne(PerfilMembro::class);
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(CursoRealizado::class);
    }

    public function setores(): BelongsToMany
    {
        return $this->belongsToMany(Setor::class, 'setor_usuario')
            ->withPivot('funcao', 'desde')->withTimestamps();
    }

    public function cargos(): BelongsToMany
    {
        return $this->belongsToMany(Cargo::class, 'cargo_usuario')->withTimestamps();
    }

    public function atributos(): BelongsToMany
    {
        return $this->belongsToMany(Atributo::class, 'atributo_usuario')
            ->withPivot('desde', 'ate')->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Sem cast 'hashed': re-hashearia hashes legados $wp$/$P$ na escrita
            // (não passam em Hash::isHashed), corrompendo a senha migrada.
            // O rehash transparente (App\Auth\HasherLegadoCema) moderniza no 1º login.
            'socio' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    protected function nomeParaIniciais(): string
    {
        return (string) $this->name;
    }
}
