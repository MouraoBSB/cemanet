# ROADMAP — Novo site do CEMA

Construção incremental, local-first. Cada fase é entregue ponta a ponta e
verificada antes da seguinte. Marque o estado ao concluir.

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
- [x] Front público: listagem `/palestras` (busca/filtro/paginação reativa via
      Livewire 4) + página individual `/palestras/{slug}` (T06, SSR + JSON-LD),
      layout base responsivo (header mega-menu/off-canvas + footer), i18n pt-BR,
      interações Alpine (compartilhar/copiar/curtir). *(Plano 4 — 38 testes verdes;
      rotas 200 e leves: home 21 KB, listagem 54 KB, single 29 KB)*
- [x] Testes (unit + feature) e verificação manual no localhost. *(front: 38 testes
      no Plano 4; admin: 63 testes no Plano 5; rotas 200 e leves)*

Pronto quando: as 123 palestras aparecem corretas no público e no admin, com
testes verdes e página leve.

## Fase 2+ — Expansão  🔄 em andamento

Ordem sugerida (cada um como nova fatia vertical):
- [x] Palestrantes (página individual e listagem) — listagem `/palestrantes`
      (busca reativa Livewire) + perfil `/palestrantes/{slug}` (bio, contato
      condicional, palestras ministradas, `schema.org/Person`); só ativos; menu
      habilitado; link na single da palestra. *(Plano 6 — 73 testes verdes)*
- [ ] Evangelho da semana + Capítulos do Evangelho
- [ ] Eventos
- [ ] Agenda Reforma Íntima (com calendário)
- [ ] Mensagens mediúnicas + Autores espirituais
- [ ] Blog (Sementeira) / Posts + Páginas institucionais
  - [ ] **Comentários** (sistema próprio, Livewire): abertos **sem conta**
        (nome + e-mail; e-mail nunca público), **login/Google opcional** com
        vantagens (selo verificado, editar o próprio, notificação). Moderação
        no Filament; 1º comentário de um e-mail fica **pendente** e, após
        aprovado uma vez, os próximos daquele e-mail **auto-publicam**.
        Anti-spam (honeypot + hCaptcha condicional + rate limit por IP) e
        **consentimento LGPD**. Sem widget de terceiro. Modelo em `DATA-MODEL.md`.
- [ ] Área de membros (taxonomia `nivel-de-acesso` → auth + roles/policies)
- [ ] Busca, formulários (contato/newsletter via Mailpit→SMTP), SEO/sitemap
- [ ] Deploy Docker no VPS (pipeline, backups do MySQL, observabilidade)
