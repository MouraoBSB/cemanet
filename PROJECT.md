# PROJECT.md — Novo site do CEMA

## Visão

Reconstruir o site do **CEMA – Centro Espírita Maria Madalena** (hoje
cemanet.org.br, em WordPress + Elementor + Jet Engine) como uma aplicação
**própria, moderna, leve e administrável**, sem WordPress. A construção é
**incremental e local-first**: cada módulo é entregue ponta a ponta (banco →
admin → páginas públicas → dados migrados) antes de seguir para o próximo.

## Problema com o site atual

- Páginas muito pesadas (~0,5 MB de HTML cada; 211 CSS / ~3,5 MB) → performance/SEO.
- Forte acoplamento a WordPress/Elementor/Jet → difícil de manter e evoluir.
- Camada de dados presa ao WP; difícil reaproveitar/integrar.

## Objetivos

- Site rápido, acessível (A11y) e otimizado para SEO desde a estrutura.
- Painel administrativo próprio (Filament) para a equipe gerenciar todo o conteúdo
  (substituindo wp-admin + Jet Engine).
- Modelo de dados limpo em MySQL, com migração fiel do conteúdo atual.
- Base portável (Docker) — mesmo ambiente no local e no VPS de produção.

## Não-objetivos (por ora)

- Não editar nem migrar o WordPress vivo (acesso a ele é **somente leitura**).
- Não reproduzir o Elementor/Jet — reconstruímos os componentes em web nativa.
- Não cobrir todos os módulos de uma vez (ver `ROADMAP.md`).

## Decisões

| Tema | Decisão | Motivo |
|---|---|---|
| Stack | Laravel 13 + Filament 5 + MySQL 8 | CMS profissional, admin pronto, time vem de PHP/WP |
| Front | Blade + Livewire + Tailwind (tokens do design-system) | SSR (SEO/perf), um ecossistema |
| Local | Docker (MySQL/Mailpit/Adminer) | "Banco próprio" reproduzível; local == produção |
| Produção | VPS Linux + Docker | Controle e portabilidade |
| 1º ciclo | Fatia vertical: módulo **Palestras** | Prova a arquitetura inteira num módulo |
| Acesso ao atual | Snapshot/design-system (design) + REST GET (dados) | Reaproveita o que já temos; sem risco ao WP vivo |
| Comentários (blog) | Sistema **próprio** (Livewire), **aberto sem conta** (nome+e-mail), login/Google **opcional**, moderado no Filament | Não trava o visitante; dados na casa (LGPD); leve (SEO/perf); sem widget de terceiro (Disqus/Facebook) |

## Inventário de conteúdo (do site atual, via REST)

| Tipo | Qtd | | Tipo | Qtd |
|---|--:|---|---|--:|
| Páginas | 21 | | Palestras públicas | 123 |
| Posts (blog) | 43 | | Palestrantes/diretores | 57 |
| Mídia | 827 | | Evangelho | 102 |
| Eventos | 53 | | Mensagens mediúnicas | 132 |
| Agenda Reforma | 52 | | Autores espirituais | 19 |

Taxonomias: `assuntos-principais` (hierárquica, ~140 termos),
`capitulos-do-evangelho`, `nivel-de-acesso` (área de membros), entre outras.

## Critérios de sucesso (módulo Palestras — 1º ciclo)

- As 123 palestras migradas para o MySQL, com palestrante(s)/diretor e assuntos.
- Admin (Filament) permite criar/editar palestras respeitando as cardinalidades.
- Listagem e página de palestra públicas, responsivas, fiéis ao design-system.
- Testes automatizados verdes + verificação manual no localhost.
- Página de palestra bem mais leve que a atual (orçamento de performance).

## Referências

- Design: `design-system/` (neste repo) + snapshot em
  `github.com/MouraoBSB/cemanet.org-wordpress`.
- Regras de migração herdadas do projeto de alimentação de palestras
  (endpoints REST, repeater de assuntos, relações 107/108).
