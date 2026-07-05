# ROADMAP — Novo site do CEMA

Construção incremental, local-first. Cada fase é entregue ponta a ponta e
verificada antes da seguinte. Marque o estado ao concluir.

## Estado atual (2026-07-04)

Módulos entregues ponta a ponta até aqui: **Fundação** (Fase 0), **Palestras**
(banco + admin + importação + público), **Palestrantes** (listagem e perfil
single), **Calendário de Palestras**, **Agenda Reforma Íntima** (banco + admin +
importação + público + SEO), **Blog "Sementeira de Luz"** (Posts + editor
RichEditor + Biblioteca de mídia), **Usuários** (RBAC + departamentos/setores/
cargos + importação do legado), **Autenticação pública** (Fortify + Google),
**Minha Conta**, **E-mail transacional** e o **tema do painel `/admin`** na
identidade CEMA. Detalhe de cada fatia nas seções abaixo.

Próximo foco pendente: **Comentários** do blog, **gate de conteúdo por nível de
acesso** (auth + roles/policies), os demais CPTs (Evangelho da semana/Capítulos,
Eventos, Mensagens mediúnicas/Autores espirituais) e o **deploy** (Docker no VPS).

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
- [ ] Eventos
- [x] **Agenda Reforma Íntima (com calendário)** — models `AgendaMetaMes`/`AgendaDia`/
      `AgendaSlugLegado`, importação idempotente do legado, admin Filament (dias/metas
      do mês/configurações), front SSR (`/agenda-reforma-intima` +
      `/agenda-reforma-intima/{data}`, redirect 301 do legado), calendário navegável,
      SEO (sitemap, JSON-LD, canonical), capa via Media Library. *(PR #5, merge `80d6ece`)*
- [ ] Mensagens mediúnicas + Autores espirituais
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
- [ ] Gate de conteúdo por **nível de acesso** (roles/policies restringindo
      páginas/palestras por taxonomia `nivel-de-acesso`) — ainda pendente.
- [x] **Tema do painel Filament** — identidade CEMA (paleta primária explícita,
      fontes Fontsource, dark mode off), polish de contraste do CTA.
      *(PR #12, merge `db942fe`; PR #13, merge `86cfa01`)*
- [ ] Busca, formulários (contato/newsletter via Mailpit→SMTP), SEO/sitemap
- [ ] Deploy Docker no VPS (pipeline, backups do MySQL, observabilidade)
