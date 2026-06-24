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

## Fase 1 — Módulo Palestras (fatia vertical)  ⬜ a fazer

Objetivo: o módulo Palestras completo, do banco ao público, com dados migrados.

- [ ] Migrations: `palestrantes`, `palestras`, `assuntos` (hierárquica),
      pivôs `palestra_pessoa` (com `papel`) e `assunto_palestra`, e
      `palestra_destaques`. Ver `DATA-MODEL.md`.
- [ ] Models Eloquent + relações + regras de cardinalidade (107/108).
- [ ] Comando `php artisan cema:importar-palestras` — lê a REST (GET), faz upsert
      idempotente das 123 palestras, resolvendo pessoas e assuntos por slug.
- [ ] Filament Resources: Palestra e Palestrante (CRUD), com validação das
      cardinalidades e upload de mídia.
- [ ] Front público: listagem de palestras + página individual, responsiva e
      fiel ao `design-system/` (ver `paginas.md` → template "Single Palestra").
- [ ] Testes (unit + feature) e verificação manual no localhost.

Pronto quando: as 123 palestras aparecem corretas no público e no admin, com
testes verdes e página leve.

## Fase 2+ — Expansão  ⬜ a fazer

Ordem sugerida (cada um como nova fatia vertical):
- [ ] Palestrantes (página individual e listagem)
- [ ] Evangelho da semana + Capítulos do Evangelho
- [ ] Eventos
- [ ] Agenda Reforma Íntima (com calendário)
- [ ] Mensagens mediúnicas + Autores espirituais
- [ ] Blog (Sementeira) / Posts + Páginas institucionais
- [ ] Área de membros (taxonomia `nivel-de-acesso` → auth + roles/policies)
- [ ] Busca, formulários (contato/newsletter via Mailpit→SMTP), SEO/sitemap
- [ ] Deploy Docker no VPS (pipeline, backups do MySQL, observabilidade)
