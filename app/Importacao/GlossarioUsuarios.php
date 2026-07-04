<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

class GlossarioUsuarios
{
    /** Papéis (slug => nível). Ordem = hierarquia linear. */
    public const PAPEIS = [
        'frequentador' => 10,
        'trabalhador' => 20,
        'diretor' => 30,
        'administrador' => 100,
    ];

    /** Departamentos (sigla => nome). */
    public const DEPARTAMENTOS = [
        'DAS' => 'Assistência Social',
        'DDA' => 'Divulgação e Artes',
        'DED' => 'Estudos Doutrinários',
        'DEMAPA' => 'Manutenção Patrimonial',
        'DEPAE' => 'Assistência Espiritual',
        'DEPRO' => 'Promoções e Eventos',
        'DIJ' => 'Infância e Juventude',
        'DECOM' => 'Comunicação e Multimídia',
    ];

    /** slug legado do setor => [nome do setor-base, sigla depto|null, funcao]. */
    public const SETORES = [
        'atendimento_fraterno' => ['Atendimento Fraterno', 'DEPAE', 'membro'],
        'medium' => ['Médium', 'DEPAE', 'membro'],
        'passista_passe_magnetico' => ['Passe Magnético', 'DEPAE', 'membro'],
        'harmonizacao' => ['Harmonização', 'DECOM', 'membro'],
        'brecho' => ['Brechó', 'DEPRO', 'membro'],
        'corte_de_verdurasopa' => ['Corte de Verduras / Sopa', 'DAS', 'membro'],
        'recepcionista' => ['Recepção', 'DAS', 'membro'],
        'caravaneiro_de_auta_de_souza' => ['Campanha Auta de Souza', 'DDA', 'membro'],
        'coordenador_da_campanha_auta_de_souza' => ['Campanha Auta de Souza', 'DDA', 'coordenador'],
        'coralista_do_cemad' => ['Coral CEMAD', 'DDA', 'membro'],
        'teluzes' => ['TELUZES (Teatro)', 'DDA', 'membro'],
        'coolaborador_decom' => ['Colaboração DECOM', 'DECOM', 'membro'],
        'evangelizador_da_infancia' => ['Evangelização da Infância', 'DIJ', 'membro'],
        'evangelizador_da_mocidade' => ['Evangelização da Mocidade', 'DIJ', 'membro'],
        'evangelizador_do_ded' => ['Evangelização (DED)', 'DED', 'membro'],
        'livraria' => ['Livraria', 'DED', 'membro'],
        'pamana' => ['PAMANA', null, 'membro'],
    ];

    /** slug legado do cargo => [nome, sigla depto|null, institucional]. */
    public const CARGOS = [
        'diretor_dda' => ['Diretor do DDA', 'DDA', false],
        'diretor_ded' => ['Diretor do DED', 'DED', false],
        'diretor_decom' => ['Diretor do DECOM', 'DECOM', false],
        'diretor_demapa' => ['Diretor do DEMAPA', 'DEMAPA', false],
        'diretor_depae' => ['Diretor do DEPAE', 'DEPAE', false],
        'diretor_depro' => ['Diretor do DEPRO', 'DEPRO', false],
        'diretor_dij' => ['Diretor do DIJ', 'DIJ', false],
        'diretor_presidente' => ['Presidente', null, true],
        'conselho_diretor' => ['Conselho Diretor', null, true],
        'conselho_fiscal' => ['Conselho Fiscal', null, true],
        'secretario' => ['Secretário', null, true],
        'tesoureiro' => ['Tesoureiro', null, true],
    ];

    /** Cargos de catálogo sem ocupante no legado (completude). */
    public const CARGOS_EXTRA = [
        'diretor_das' => ['Diretor do DAS', 'DAS', false],
    ];
}
