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
| Acesso ao atual | Snapshot/design-system (design) + banco `legado` via túnel SSH, somente leitura (fonte PREFERIDA de dados — ver `DB-LEGADO.md`) + REST GET (alternativa/complemento) | Banco legado é mais rico (schema real, postmeta, relações Jet, repeaters serializados); REST cobre o que o túnel não expõe |
| Comentários (blog) | Sistema **próprio** (Livewire), **aberto sem conta** (nome+e-mail), login/Google **opcional**, moderado no Filament | Não trava o visitante; dados na casa (LGPD); leve (SEO/perf); sem widget de terceiro (Disqus/Facebook) |
| Editor do blog | **RichEditor** (TipTap/HTML) nativo do Filament 5, com **alinhamento/float e redimensionamento de imagem** | Migração simples (preserva o HTML do Gutenberg); edição tipo Word; foto **ao lado do texto** (contorno) e **tamanho ajustável**; layouts ricos via bloco/extensão quando necessário |
| Mídia/imagens | **Spatie Media Library**: guarda o **original preservado** no disco (capado a ≤2000px no lado maior; ≤1200px na coleção `og`) + conversões **WebP** (`web` e `thumb`, síncronas) geradas pelo trait `App\Models\Concerns\RegistraImagensPadrao`; upload múltiplo e reordenável no Filament | Mantém o arquivo de origem como fallback/reprocessamento; o site consome só as conversões WebP otimizadas; padrão Laravel, reutilizável por qualquer model `HasMedia` |
| Biblioteca de mídia | **Sobre a Media Library (Opção B):** pool central, imagens **referenciadas por URL** (não posse do post); dedup por hash; tool "Inserir da biblioteca" no editor | Um só sistema de mídia; reusa cap+conversões; **generaliza o fix do corpo** (referência → some a classe de bug do editor); Curator traria 2º sistema sem ganho turnkey no editor |
| Referência de mídia portável | Mídia usada **no conteúdo** é servida por **rota estável do app** (resolve o storage atual), não por caminho cru `/storage/...` | Permite migrar o storage (S3/CDN) no futuro **sem quebrar** as imagens já dentro dos posts |
| Autorização — 2 eixos | **VISIBILIDADE** ("quem vê") × **CAPACIDADE** ("quem edita") são mecanismos **distintos** | Achatá-los num "papel" só (como o WP fazia) foi a origem da confusão; separados, cada um evolui sozinho |
| Painel × site | O **`/admin` é exclusivo de administrador**; o não-admin (diretor/colaborador) edita **pelo site**, em `/minha-conta` | Painel é ferramenta de governança; a casa não precisa treinar diretor em wp-admin. O site já é o ambiente deles |
| Capacidade de escrita | **3 condições, fail-closed**: capacidade (papel→permissão) **+** vínculo do usuário a um departamento **+** o objeto pertencer a um departamento em comum | Delegação real por departamento sem inventar hierarquia; faltando qualquer uma, nega |
| Matriz papel×capacidade | Tela dedicada (`/admin/matriz-capacidades`) é o **único escritor** de `role_has_permissions`; ligar capacidade é **cutover manual por ambiente** | Uma fonte de verdade auditável; nenhum seeder/comando liga permissão pelas costas |
| Fonte única do formulário | O schema do form (`App\Filament\Schemas\*Form::schema()`) é **um só**, consumido pelo painel **e** pelo site | O mesmo campo/regra não pode divergir entre as duas superfícies |
| Campos privilegiados | Fora do `/admin`, `departamentos`/`status` são **forçados/reasseridos no servidor** — nunca vindos do POST | Impede escalonamento de privilégio por payload forjado |
| Auditoria | `spatie/laravel-activitylog`, trilha **append-only**, com **porta** (`admin`/`sistema`/`perfil`) + IP + user-agent | Saber **quem** mudou **o quê**, **de onde** — sem depender de log de aplicação |

Desde o 1º ciclo (Palestras), o projeto já entregou ponta a ponta os módulos
Palestrantes, Calendário de Palestras, Agenda Reforma Íntima, Blog "Sementeira
de Luz" + Biblioteca de Mídia, **Eventos** (+ calendário unificado e feed `.ics`),
Usuários (estrutura organizacional e papéis), Autenticação, Minha Conta, e-mail
transacional, o tema do painel `/admin` e o **modelo de capacidades** — a
autorização de escrita ponta a ponta (matriz papel×capacidade, departamento como
filtro de objeto, auditoria append-only e a edição de conteúdo pelo próprio site,
com a Agenda como piloto). O estado fase a fase vive em [ROADMAP.md](ROADMAP.md).

## Inventário de conteúdo (do site atual, via REST)

| Tipo | Qtd | | Tipo | Qtd |
|---|--:|---|---|--:|
| Páginas | 21 | | Palestras públicas | 123 |
| Posts (blog) | 44¹ | | Palestrantes/diretores | 57 |
| Mídia | 827 | | Evangelho | 102 |
| Eventos | 53 | | Mensagens mediúnicas | 132 |
| Agenda Reforma | 52 | | Autores espirituais | 19 |

¹ Contagem original via REST era 43; a importação real do banco `legado`
trouxe 44 posts publicados (ver `DATA-MODEL.md` e `CLAUDE.md`).

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
