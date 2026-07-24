# ROADMAP — Novo site do CEMA

Construção incremental, local-first. Cada fase é entregue ponta a ponta e
verificada antes da seguinte. Marque o estado ao concluir.

## Estado atual (2026-07-21)

Módulos entregues ponta a ponta até aqui: **Fundação** (Fase 0), **Palestras**
(banco + admin + importação + público), **Palestrantes** (listagem e perfil
single), **Calendário de Palestras**, **Agenda Reforma Íntima** (banco + admin +
importação + público + SEO), **Blog "Sementeira de Luz"** (Posts + editor
RichEditor + Biblioteca de mídia), **Eventos** (banco + admin + importação +
front + feed `.ics` + calendário unificado), **Usuários** (RBAC + departamentos/
setores/cargos + importação do legado), **Autenticação pública** (Fortify +
Google), **Minha Conta**, **E-mail transacional**, o **tema do painel `/admin`**
na identidade CEMA, o **modelo de capacidades** — autorização de escrita ponta a
ponta: matriz papel×capacidade, departamento como filtro de objeto, auditoria
append-only e a **edição de conteúdo pelo site** (`/minha-conta`), com a Agenda
como piloto (Fases A→D) — e **Mensagens mediúnicas + Autores espirituais**
(Camada 4: banco + admin + importação + front público + visibilidade rica +
"Minhas Direcionadas" + curadoria e lançamento pelo site, Fatia F4b). Detalhe de
cada fatia nas seções abaixo.

Próximo foco pendente: **Fase E** (replicar a edição no `/minha-conta` para
Blog/Eventos/Palestras, no padrão do piloto da Agenda), **Comentários** do blog,
**gate de conteúdo por nível de acesso** nos módulos que ainda não têm (Eventos e
Mensagens já têm visibilidade própria), os demais CPTs (Evangelho da semana/
Capítulos) e o **deploy** (Docker no VPS).

## Fase 0 — Fundação  ✅ concluída

Objetivo: ambiente reproduzível rodando, com Laravel + Filament + MySQL.

- [x] `docker compose up -d` (sobe MySQL, Mailpit, Adminer).
- [x] Copiar `.env.example` → `.env` e preencher (incluindo `CEMA_WP_*` para a
      importação — somente leitura).
- [x] Scaffold do Laravel na raiz **sem PHP local** (via container composer),
      preservando os arquivos de planejamento e `design-system/`. Ex.:
      `docker run --rm -v "$PWD":/app -w /app composer:2 create-project laravel/laravel _tmp "13.*"`
      e mesclar `_tmp/` na raiz sem sobrescrever os docs existentes.
- [x] `php artisan key:generate`, configurar conexão MySQL e rodar `migrate`.
- [x] Instalar **Filament 5** e criar o primeiro usuário admin.
- [x] Instalar **Tailwind** e mapear os tokens de `design-system/tokens.json`
      (cores, tipografia Work Sans/Poppins, espaçamentos, breakpoints).
- [x] `git` inicializado, primeiro commit, CI mínimo (lint + testes).

Pronto quando: `localhost` abre o Laravel, o admin do Filament loga e `artisan test` passa.

## Fase 1 — Módulo Palestras (fatia vertical)  ✅ concluída

Objetivo: o módulo Palestras completo, do banco ao público, com dados migrados.

- [x] Migrations: `palestrantes`, `palestras`, `assuntos` (hierárquica),
      pivôs `palestra_pessoa` (com `papel`) e `assunto_palestra`, e
      `palestra_destaques`. Ver `DATA-MODEL.md`. *(Plano 1)*
- [x] Models Eloquent + relações + regras de cardinalidade (107/108). *(Plano 1)*
- [x] Comando `php artisan cema:importar-palestras` — lê o banco **legado** (read-only),
      faz upsert idempotente das 123 palestras, resolvendo pessoas e assuntos por slug.
      *(Plano 2 — 123 palestras, 57 palestrantes, 141 assuntos importados; idempotente)*
- [x] Filament Resources: Palestra, Palestrante e Assunto (CRUD), com validação da
      cardinalidade (1–2 palestrantes / 0–1 diretor, nunca órfã) e upload de mídia.
      Sanitização de `descricao`/`bio` (mews/purifier, mutator no model — cobre admin
      e importador) e validação de `cor_fundo` (hex). *(Plano 5 — 63 testes verdes;
      painel `/admin` com acesso gateado por ambiente)*
      **Hardening pendente p/ produção (Fase 2):** gate de acesso ao painel por
      papel/role (hoje `canAccessPanel` libera local/testing e bloqueia produção);
      prevenção de ciclos profundos na taxonomia; re-sanitizar as 123 `descricao` já
      importadas (rodar a importação idempotente 1× pós-deploy normaliza pelo mutator).
      Já corrigido: `allowed_classes=false` no `unserialize` do `TransformadorLegado`
      (fix de segurança mesclado).
- [x] Front público: listagem + página individual em **`/palestra_publica`** (URL
      compatível com o WP; redirect 301 de `/palestras`), layout base responsivo
      (header mega-menu/off-canvas + footer), i18n pt-BR, interações Alpine.
      *(Plano 4 — base; Plano 7 — redesign: destaque da próxima palestra, cards
      menores com capa do YouTube, filtros reativos título/data/palestrante/assunto +
      total; 85 testes verdes)*
- [x] Testes (unit + feature) e verificação manual no localhost. *(front: 38 testes
      no Plano 4; admin: 63 testes no Plano 5; rotas 200 e leves)*

Pronto quando: as 123 palestras aparecem corretas no público e no admin, com
testes verdes e página leve.

## Redesign incremental do front público  🔄 em andamento

Refinamentos hi-fi do front (design-system), fatia a fatia, sobre os módulos já
entregues. Cada fatia: spec + plano (`docs/superpowers/`) → subagent-driven-development
→ revisão → PR (CI verde) → merge.

- [x] **Palestras — listagem/archive** `/palestra_publica`: card-pôster 16:10, barra de
      filtros redesenhada (chips de filtros ativos, limpar/remover), visões grade/lista,
      banner "Próxima palestra" + "Veja também" + JSON-LD. *(PR #1, merge `d446e18`)*
- [x] **Calendário de Palestras** `/palestra_publica/calendario`: página Livewire
      (destaque da próxima + tabs Próximas/Realizadas + navegação mês/ano + mini-calendário
      indexador + agenda), **feed `.ics`/webcal** agregado + modal "Assinar", `FeedIcs`
      compartilhado (dobra RFC 5545), JSON-LD `ItemList`/`Event`. *(PR #2, merge `1cd5c4c`;
      suíte completa 352 verdes)*
- [x] **Palestrantes — redesign da listagem** `/palestrantes`: hero na nova identidade,
      grade de cards (avatar da foto ou **iniciais** em gradiente + badge de contagem de
      palestras), busca reativa + ordenação (A–Z / Z–A / mais / menos), sidebar (intro +
      stats reais + card "Em destaque" da próxima palestra), estado vazio, paginação 12.
      **Sem filtro de área nesta fase** (o handoff presume uma taxonomia de área fictícia;
      `Palestrante` não tem campo `area` — feature adiada para quando houver taxonomia real).
      *(PR #3, merge `9d556cd`; suíte completa 370 verdes)*
- [x] **Palestrante — detalhe (single)** `/palestrantes/{slug}`: hero roxo (foto 3:4 em
      moldura ou **iniciais** em gradiente, eyebrow, H1, **frase de chamada** — coluna
      `chamada`), chips das áreas + CTA calendário, bloco de estatísticas reais
      (palestras · temas · ativo desde · % online), "Sobre" com prosa, grade de palestras
      reaproveitando `<x-palestra.card>` com **filtro por tema + ordenação client-side
      (Alpine)**, e sidebar sticky (próxima palestra em destaque · áreas de atuação clicáveis
      · compartilhar). Áreas = `assuntos` distintos (**sem** taxonomia de "área" fictícia);
      cor rotacionada `id % 8`. Migração **aditiva** `chamada` (nunca `migrate:fresh`).
      *(PR #4, merge `15663ca`)*

## Fase 2+ — Expansão  🔄 em andamento

Ordem sugerida (cada um como nova fatia vertical):
- [x] Palestrantes (página individual e listagem) — listagem `/palestrantes`
      (busca reativa Livewire) + perfil `/palestrantes/{slug}` (bio, contato
      condicional, palestras ministradas, `schema.org/Person`); só ativos; menu
      habilitado; link na single da palestra. *(Plano 6 — 73 testes verdes)*
- [x] **Padrão de mídia (Spatie Media Library)** — adotado como padrão transversal:
      Post (destacada/galeria/og/corpo), Palestrante (foto), Agenda (capa), User/Conta
      (foto de perfil); original preservado no disco + conversões WebP (`web`/`thumb`)
      geradas pelo trait reutilizável `App\Models\Concerns\RegistraImagensPadrao`.
      *(commits `1203cbd`, `00f57ba`, `958f84e`, `6c028e2`)*
- [ ] Evangelho da semana + Capítulos do Evangelho
- [x] **Eventos** — models `Evento`/`CategoriaEvento` (+ `departamento_evento`),
      importação idempotente do legado (`cema:importar-eventos`), admin Filament
      (form em fonte única `App\Filament\Schemas\EventoForm`), front SSR
      (listagem + single), **feed `.ics`/webcal** e **calendário unificado**
      (palestras + eventos + agenda). Visibilidade própria por enum
      `VisibilidadeEvento` (`publico`/`logados`/`trabalhadores`/`diretoria`).
      *(PRs #19 importador, #20 front, #21/#22 `.ics`, #23 calendário unificado,
      #24 `EventoForm` — merge `cb2fd48`)*
- [x] **Agenda Reforma Íntima (com calendário)** — models `AgendaMetaMes`/`AgendaDia`/
      `AgendaSlugLegado`, importação idempotente do legado, admin Filament (dias/metas
      do mês/configurações), front SSR (`/agenda-reforma-intima` +
      `/agenda-reforma-intima/{data}`, redirect 301 do legado), calendário navegável,
      SEO (sitemap, JSON-LD, canonical), capa via Media Library. *(PR #5, merge `80d6ece`)*
- [x] **Mensagens mediúnicas + Autores espirituais** (Camada 4) — models `Mensagem`/
      `AutorEspiritual` + pivôs (autores, relacionadas, destinatários, departamento),
      importação idempotente do legado (`cema:importar-mensagens`, `-autores-espirituais`,
      `-direcionadas`), admin Filament (`MensagemResource`/`AutorEspiritualResource`, DoTipo,
      campo de destinatários), front SSR (listas + single de Mensagem e de Autor Espiritual,
      SEO/sitemap), **visibilidade rica** por enum `VisibilidadeMensagem` (6 níveis —
      Público/Trabalhadores/Médiuns/Diretores/Diretor-DEPAE/Direcionada: escada de papel +
      recortes por pertencimento + barreira de login no single), aba **"Minhas Direcionadas"**
      (read-only, `/minha-conta/direcionadas`) e, agora (**Fatia F4b**), a **produção no
      próprio site**: o médium lança em `/minha-conta/mensagens` (nasce sempre pendente, com
      `medium_id` e `nivel = null`) e o diretor do DEPAE (ou o presidente) cura em
      `/minha-conta/curadoria` — fila de pendentes, histórico do item e o martelo **Publicar**,
      que arbitra o nível de acesso. Eixo de autoria por **pertencimento a setor/cargo**
      (`MensagemPolicy`), não pela matriz de capacidades (`mensagem.*` inertes); trilha própria
      em `activity_log` (`log_name='mensagem'`, corpo/resumo redigidos na escrita) e autoria
      privilegiada (`medium_id`/`publicado_por_id`/`publicado_em`, forçados no servidor).
      Fecharam o ciclo a **Fatia F4c-AC** — o `resumo` do legado (`post_excerpt`, importado por
      `cema:importar-resumos`), imagens nos 3 formatos (coleção `pictografia` → `imagens`) e a
      Action **Publicar** no `/admin`, que passou a exigir a mesma regra de negócio do site — e a
      **Fatia F4c-D**, que **fundiu `contexto` em `resumo`** (as duas colunas eram o mesmo texto
      editorial, renderizado em dois lugares da mesma página): sobrou o `resumo`, agora também
      escrito pelo médium, com render único no lead; a coluna `contexto` foi dropada por par de
      migrations (funde → dropa).
      *(Fatias 0→F4a: PR #35 merge `c988f89`, #36 merge `ef8841b`, #37 merge `161b502`,
      #38 merge `b7f9402`, #39 merge `0fa26c4`, #40 merge `c517b70`, #41 merge `93999e8`,
      #42 merge `8142883`; F4b: PR #43 merge `45a9eb7`; F4c-AC: PR #44 merge `4c3e5d5`;
      F4c-D: PR #45 merge `8b2c03f`.)*
- [x] **Mensagens de validação em pt-BR** — transversal, entregue de carona na Fatia F4c-D.
      `lang/pt_BR/validation.php` (as 107 regras do canônico da versão instalada, com as
      sub-chaves de tamanho e de `password`), `auth.php` e `passwords.php`. A causa raiz **não
      era o `APP_LOCALE`** (já é `pt_BR` no `.env`): faltavam os arquivos, então toda chave caía
      no `fallback_locale = en`. Vale para o `/admin`, para os formulários do site, para o
      Fortify e dentro da suíte. A seção `attributes` é **inerte dentro do Filament** (lá o
      `:attribute` vem do `->label()`) e cobre só o que é validado fora dele — Fortify e o
      `EditarPerfil` de `/minha-conta/perfil`. Completude travada por
      `Tests\Feature\Idioma\ValidationPtBrTest`, que compara as chaves com o canônico do
      `vendor/` de forma recursiva (menos `custom`/`attributes`) e **fica vermelho sozinho**
      quando um `composer update` trouxer regra nova.
      *(F4c-D: PR #45 merge `8b2c03f`.)*
- [ ] **Blog (Sementeira de Luz)** — módulo **Posts entregue** (admin + front + posts
      importados + editor/mídia abaixo). Pendentes: **Comentários** e **Páginas institucionais**.
  - [x] **Editor + mídia** (RichEditor TipTap): justificado padrão + alinhamento/tamanho de
        imagem por classes (preserva as classes do Gutenberg no CSS); ferramentas nativas
        (grid/lead/hr/clearFormatting/textColor); rodapé sticky; "publicar agora". **Performance**
        do upload (3 tetos alinhados em ~20 MB, conversão síncrona). **Biblioteca de mídia**
        reutilizável: rota portável `/midia/{id}/web` (escopo/allowlist/cache ramificado),
        dedup SHA-256, metadados (alt/legenda/título/descrição), deleção autoritativa, tool
        "Inserir da biblioteca" (escolher da grade / subir nova) que conserta a imagem do corpo
        no front e substitui o clipe (anexos desativados no corpo). Fix de disco (uploads do
        painel em `public`). *(merges: editor UX, Fatia A performance, Fatia B biblioteca;
        252 testes PHP + 5 JS verdes)*
        - **Backlog adiado:** re-migração dos posts (corpo → referências da biblioteca, p/
          portabilidade S3/CDN, com dry-run+backup); render rico de metadados (figcaption +
          JSON-LD `ImageObject`); "Gerar alt" por IA; colar/arrastar → biblioteca; polish do
          textColor (retorno visual da cor).
  - [ ] **Comentários** (sistema próprio, Livewire): abertos **sem conta**
        (nome + e-mail; e-mail nunca público), **login/Google opcional** com
        vantagens (selo verificado, editar o próprio, notificação). Moderação
        no Filament; 1º comentário de um e-mail fica **pendente** e, após
        aprovado uma vez, os próximos daquele e-mail **auto-publicam**.
        Anti-spam (honeypot + hCaptcha condicional + rate limit por IP) e
        **consentimento LGPD**. Sem widget de terceiro. Modelo em `DATA-MODEL.md`.
- [x] **Usuários** — modelo de classificação/autorização (spatie/laravel-permission +
      `nivel` em roles), catálogos departamentos/setores/cargos, importação idempotente
      do legado (hasher phpass/$wp$, `cema:importar-usuarios`), Filament Resources
      (Usuário/Departamento/Setor/Cargo), gate do painel por papel. *(PR #7, merge `982889b`)*
- [x] **Autenticação pública** (login por senha, cadastro aberto, Google OAuth via
      Socialite, reset de senha pt-BR, Fortify headless). *(PR #8, merge `212fdfe`;
      e-mail transacional PR #9, merge `4cc57fc`)*
- [x] **Minha Conta** (área self-service: Painel, Meu Perfil com edição/foto+cropper,
      header auth-aware). *(PR #10, merge `1a7a2ab`)*
- [ ] Gate de conteúdo por **nível de acesso** (restringir páginas/palestras por
      taxonomia `nivel-de-acesso`) — pendente **nesses módulos**. Eventos já tem
      visibilidade própria (enum `VisibilidadeEvento`). É o eixo **VISIBILIDADE**
      ("quem vê"), distinto do eixo **CAPACIDADE** ("quem edita"), este resolvido
      pelas Fases A→D (seção abaixo).
- [x] **Tema do painel Filament** — identidade CEMA (paleta primária explícita,
      fontes Fontsource, dark mode off), polish de contraste do CTA.
      *(PR #12, merge `db942fe`; PR #13, merge `86cfa01`)*
- [ ] Busca, formulários (contato/newsletter via Mailpit→SMTP), SEO/sitemap
- [ ] Deploy Docker no VPS (pipeline, backups do MySQL, observabilidade)

## Modelo de capacidades — quem EDITA o conteúdo (Fases A→D)  ✅ concluído

Dois eixos ortogonais: **VISIBILIDADE** ("quem vê" — audiência/nível de acesso) ×
**CAPACIDADE** ("quem edita"). Este arco resolveu a **CAPACIDADE** e abriu a edição
a não-admin **pelo site**, mantendo o `/admin` **exclusivo de administrador**
(`User::canAccessPanel` → `hasRole('administrador')` é o único portão do painel).

Três condições, **todas** exigidas para um não-admin editar um objeto (fail-closed):
**capacidade** (papel→permissão, via matriz) **+ vínculo** do usuário a um
departamento (`departamento_usuario`) **+ objeto** pertencer a um departamento em
comum (pivô `departamento_<conteudo>`). O admin passa antes, no `Gate::before`.

- [x] **Fase A — modelo de capacidades**: 20 permissions `recurso.acao` (guard `web`;
      `evento`/`palestra`/`post`/`agenda`/`palestrante` × `ver`/`criar`/`editar`/`excluir`),
      `GlossarioCapacidades` + `CapacidadesSeeder`, pivô `departamento_usuario`, contrato
      `TemDepartamento`, trait `AutorizaPorDepartamento` (interseção, fail-closed) e as 5
      policies (`hasPermissionTo`, **nunca** `can()`). *(PR #25, merge `08472c3`)*
- [x] **Fase B — departamento nos conteúdos**: pivôs `departamento_{palestra,post,
      palestrante,agenda_dia}` (Evento já tinha) + backfill idempotente
      (`cema:departamentalizar-conteudos`, `cema:vincular-diretores-departamento`).
      *(PR #26, merge `d7696b8`)*
- [x] **Fase C — matriz papel×capacidade**: página Filament **`/admin/matriz-capacidades`**
      (grade 20 capacidades × papéis `trabalhador`/`diretor`), **único escritor** de
      `role_has_permissions` (`Role::syncPermissions`); `Select` de departamentos no
      `UserResource` e nos 4 conteúdos. *(PR #27, merge `77000e8`)*
- [x] **Fase de Auditoria (activitylog)**: `spatie/laravel-activitylog`, tabela
      `activity_log`, helper `AuditoriaAutorizacao` (porta/contexto/diff), trait
      `LogsActivity` no `User` + log manual dos 3 pivôs de autorização. Toda entrada
      carrega **porta** (`admin` | `sistema` | `perfil`) + IP + user-agent.
      *(PR #28, merge `c316527`)*
- [x] **Fase D — edição da Agenda no `/minha-conta`** (piloto do "não-admin edita pelo
      site"): `AgendaDiaForm::schema()` como **fonte única** (painel + site), **tema
      Filament escopado** ao site (sem preflight/Inter), rota irmã `conta.agenda` + aba
      condicional, componente `AgendaConta` (lista escopada ao depto + criar/editar/
      excluir) com o **campo privilegiado forçado no servidor** (`status` — quem não tem
      `agenda.editar` só cria rascunho; `departamentos` deixou de ser forçado no E2), trait
      `LogsActivity` no `AgendaDia` (7 campos, `log_name='agenda'`) e **porta `'perfil'`**
      marcada no `boot()` do componente. *(D1: PR #29, merge `515ff74`; D2: PR #30,
      merge `80af57d`)*
      - **Correções de dados** (idempotentes, já aplicadas no dev): `cema:corrigir-papel-diretores`,
        `cema:somar-ded-agenda` (soma DED aos 123 dias, preserva DECOM),
        `cema:vincular-presidentes-departamentos` (presidentes → 8 deptos).
      - ⚠️ **Cutover (por ambiente)**: rodar os 3 comandos **+ ligar `agenda.*` para
        `diretor`/`trabalhador` na matriz pela UI**. Sem esse passo o modelo não "morde"
        (não há comando que escreva `role_has_permissions` — é invariante da Fase C).
- [ ] **Fase E** — replicar o padrão da Fase D para **Blog / Eventos / Palestras**
      (fonte única do form + superfície no `/minha-conta` + campos privilegiados forçados
      + auditoria). Backlog técnico herdado de D: teste do catch `QueryException` 23000;
      extrair `diffPorId` no helper; nota de Octane no docblock de `$portaForcada`;
      `DB::transaction` no `create()+sync()+log`.
