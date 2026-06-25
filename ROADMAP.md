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

## Fase 1 — Módulo Palestras (fatia vertical)  🔄 em andamento

Objetivo: o módulo Palestras completo, do banco ao público, com dados migrados.

- [x] Migrations: `palestrantes`, `palestras`, `assuntos` (hierárquica),
      pivôs `palestra_pessoa` (com `papel`) e `assunto_palestra`, e
      `palestra_destaques`. Ver `DATA-MODEL.md`. *(Plano 1)*
- [x] Models Eloquent + relações + regras de cardinalidade (107/108). *(Plano 1)*
- [x] Comando `php artisan cema:importar-palestras` — lê o banco **legado** (read-only),
      faz upsert idempotente das 123 palestras, resolvendo pessoas e assuntos por slug.
      *(Plano 2 — 123 palestras, 57 palestrantes, 141 assuntos importados; idempotente)*
- [ ] Filament Resources: Palestra e Palestrante (CRUD), com validação das
      cardinalidades e upload de mídia.
      **Pré-requisitos de segurança (revisão do Plano 4):** sanitizar `descricao`
      (HTML do legado/editável) com allow-list na escrita (ex.: HTMLPurifier) e
      validar `cor_fundo` (formato hex/rgb) — hoje a single renderiza `descricao`
      com `{!! !!}` confiando em conteúdo de staff; ao abrir edição no admin, isso
      vira superfície de XSS armazenado.
- [x] Front público: listagem `/palestras` (busca/filtro/paginação reativa via
      Livewire 4) + página individual `/palestras/{slug}` (T06, SSR + JSON-LD),
      layout base responsivo (header mega-menu/off-canvas + footer), i18n pt-BR,
      interações Alpine (compartilhar/copiar/curtir). *(Plano 4 — 38 testes verdes;
      rotas 200 e leves: home 21 KB, listagem 54 KB, single 29 KB)*
- [ ] Testes (unit + feature) e verificação manual no localhost. *(front coberto
      no Plano 4; admin pendente)*

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
