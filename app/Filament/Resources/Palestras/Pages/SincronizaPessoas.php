<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources\Palestras\Pages;

use App\Models\Palestra;

trait SincronizaPessoas
{
    /** @var array<string, mixed> */
    protected array $pessoasSelecionadas = [];

    protected function capturarPessoas(array $data): array
    {
        $this->pessoasSelecionadas = [
            'ids_palestrantes' => $data['ids_palestrantes'] ?? [],
            'id_diretor' => $data['id_diretor'] ?? null,
        ];

        unset($data['ids_palestrantes'], $data['id_diretor']);

        return $data;
    }

    protected function sincronizarPessoas(Palestra $palestra): void
    {
        $idsPalestrantes = array_values(array_filter(array_map('intval', (array) ($this->pessoasSelecionadas['ids_palestrantes'] ?? []))));
        $idDiretor = $this->pessoasSelecionadas['id_diretor'] ?? null;
        $idDiretor = $idDiretor !== null ? (int) $idDiretor : null;

        if ($idDiretor && in_array($idDiretor, $idsPalestrantes, true)) {
            $idDiretor = null;
        }

        $sync = [];
        foreach ($idsPalestrantes as $id) {
            $sync[$id] = ['papel' => Palestra::PAPEL_PALESTRANTE];
        }
        if ($idDiretor) {
            $sync[$idDiretor] = ['papel' => Palestra::PAPEL_DIRETOR];
        }

        $palestra->palestrantes()->sync($sync);
    }
}
