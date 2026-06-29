<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Support\Biblioteca;

use App\Models\Biblioteca;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Registra um arquivo na biblioteca com dedup por SHA-256 (duas etapas, robusto ao cap)
 * e metadados opcionais (alt/legenda/titulo/descricao) em custom_properties.
 */
class RegistraMidiaBiblioteca
{
    private const CAMPOS_META = ['alt', 'legenda', 'titulo', 'descricao'];

    public function aPartirDoCaminho(string $caminho, ?string $nome = null, array $meta = []): Media
    {
        // 1) Caminho rápido: hash do arquivo de entrada. Se ele já cabe no teto (não capa),
        //    seu hash == hash canônico pós-cap → acha duplicata sem recriar.
        $hashEntrada = hash_file('sha256', $caminho);
        if ($existente = $this->buscarPorHash($hashEntrada)) {
            $this->preencherMetadadosVazios($existente, $meta);
            return $existente;
        }

        // 2) Não achou → adiciona (dispara cap + cálculo do hash canônico pós-cap).
        $media = Biblioteca::instance()
            ->addMediaFromString(file_get_contents($caminho))
            ->usingFileName($nome ?? basename($caminho))
            ->toMediaCollection(Biblioteca::COLECAO);
        $media->refresh();

        // 3) Reverifica pelo hash canônico (pós-cap): entrada era > teto e capou para algo
        //    já presente → descarta a recém-criada e devolve a existente.
        $hashCanonico = $media->getCustomProperty('sha256');
        if ($hashCanonico && $duplicata = $this->buscarPorHash($hashCanonico, $media->id)) {
            $media->delete();
            $this->preencherMetadadosVazios($duplicata, $meta);
            return $duplicata;
        }

        $this->preencherMetadadosVazios($media, $meta);
        return $media;
    }

    private function buscarPorHash(string $hash, ?int $exceto = null): ?Media
    {
        return Media::query()
            ->where('collection_name', Biblioteca::COLECAO)
            ->where('custom_properties->sha256', $hash)
            ->when($exceto, fn ($q) => $q->where('id', '!=', $exceto))
            ->first();
    }

    /** Preenche só os metadados ainda vazios (não sobrescreve curadoria existente). */
    private function preencherMetadadosVazios(Media $media, array $meta): void
    {
        $alterou = false;
        foreach (self::CAMPOS_META as $campo) {
            if (filled($meta[$campo] ?? null) && blank($media->getCustomProperty($campo))) {
                $media->setCustomProperty($campo, $meta[$campo]);
                $alterou = true;
            }
        }
        if ($alterou) {
            $media->saveQuietly();
        }
    }
}
