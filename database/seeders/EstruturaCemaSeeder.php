<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace Database\Seeders;

use App\Importacao\GlossarioUsuarios;
use App\Models\Atributo;
use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\Setor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class EstruturaCemaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (GlossarioUsuarios::PAPEIS as $slug => $nivel) {
            Role::updateOrCreate(
                ['name' => $slug, 'guard_name' => 'web'],
                ['nivel' => $nivel],
            );
        }

        $ordem = 0;
        foreach (GlossarioUsuarios::DEPARTAMENTOS as $sigla => $nome) {
            Departamento::updateOrCreate(
                ['slug' => Str::slug($sigla)],
                ['sigla' => $sigla, 'nome' => $nome, 'ordem' => $ordem++],
            );
        }

        $siglaParaId = Departamento::pluck('id', 'sigla');

        foreach (GlossarioUsuarios::SETORES as [$nome, $sigla, $funcao]) {
            Setor::updateOrCreate(
                ['slug' => Str::slug($nome)],
                ['nome' => $nome, 'departamento_id' => $sigla ? $siglaParaId[$sigla] : null],
            );
        }

        $cargos = GlossarioUsuarios::CARGOS + GlossarioUsuarios::CARGOS_EXTRA;
        foreach ($cargos as [$nome, $sigla, $institucional]) {
            Cargo::updateOrCreate(
                ['slug' => Str::slug($nome)],
                [
                    'nome' => $nome,
                    'departamento_id' => $sigla ? $siglaParaId[$sigla] : null,
                    'institucional' => $institucional,
                ],
            );
        }

        Atributo::updateOrCreate(['slug' => 'socio'], ['nome' => 'Sócio']);
    }
}
