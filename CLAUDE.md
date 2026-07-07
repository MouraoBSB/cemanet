# CLAUDE.md — Novo site do CEMA (Centro Espírita Maria Madalena)

Leia antes de agir. Visão e escopo: ver [PROJECT.md](PROJECT.md).
Fases e estado atual: ver [ROADMAP.md](ROADMAP.md).
Modelo de dados e regras de negócio: ver [DATA-MODEL.md](DATA-MODEL.md).
**Imagens (padrão único WebP): ver [IMAGENS.md](IMAGENS.md).**
Referência visual: pasta [design-system/](design-system/).

## O que é este projeto

Reconstrução do site **cemanet.org.br** (CEMA – Centro Espírita Maria Madalena)
**SEM WordPress**: uma aplicação própria, construída de forma **incremental no
localhost**, fatia vertical por fatia vertical (banco → admin → páginas
públicas → migração de dados). A fatia inicial (**Palestras**) provou a
arquitetura; hoje o projeto já cobre também Agenda, Blog "Sementeira de Luz",
Biblioteca de Mídia, Usuários, autenticação pública (senha + Google), Minha
Conta, e-mail transacional e o tema do painel `/admin` — estado atual sempre
em [ROADMAP.md](ROADMAP.md).

O site atual roda em WordPress + Elementor + Jet Engine; este projeto o
substitui por uma base moderna, leve, administrável e mantível.

## Stack (fixa)

- **PHP 8.3 · Laravel 13 · Filament 5 · MySQL 8**
- **Vite + Tailwind** (alimentado pelos tokens em `design-system/tokens.json`)
- **Blade + Livewire** no front público (SSR por padrão → SEO e performance)
- **Docker** no local — serviços `app` (PHP 8.3, mesma imagem da produção),
  `worker` (queue), `db` (MySQL), `mailpit` e `adminer`, via
  `docker-compose.yml`; produção em **VPS Linux + Docker** (local == produção)
- **Autenticação/RBAC**: Laravel Fortify (headless) + Socialite (Google OAuth)
  + spatie/laravel-permission (papéis/roles)
- **Mídia**: spatie/laravel-medialibrary — original preservado (capado a
  ≤2000px; ≤1200px na coleção `og`) + conversões WebP `web`/`thumb` geradas
  pelo trait `App\Models\Concerns\RegistraImagensPadrao`; a Biblioteca do blog
  aplica dedup por SHA-256

## Idioma

Tudo em **português brasileiro**: código (identificadores de domínio),
comentários, mensagens de interface/erro, commits e respostas. A sintaxe da
linguagem e APIs de terceiros ficam no original.

## Acesso ao site atual (SOMENTE LEITURA)

Três insumos já existem; use-os, não recrie:

1. **Design** — `design-system/` (tokens, componentes, mapa de páginas) e, para
   fidelidade pixel a pixel, o repositório do snapshot
   `github.com/MouraoBSB/cemanet.org-wordpress` (pasta `snapshot/`).
2. **Conteúdo — banco `legado` (FONTE PREFERIDA p/ importação)** — conexão **`legado`
   somente leitura** ao MySQL do WordPress atual, via **túnel SSH**, com usuário
   **só-SELECT** (variáveis `LEGADO_DB_*`). É a fonte **preferida** por ser mais rica
   e completa que a REST: schema real, `wp_postmeta`, relações Jet `wp_jet_rel_107/108`
   (triviais via SQL), repeaters serializados e taxonomias. Mapa WP→MySQL já
   documentado em **[DB-LEGADO.md](DB-LEGADO.md)**.
3. **Conteúdo — REST (alternativa/complemento, ainda sem uso real)** — a **REST API**
   de cemanet.org.br (Application Password no `.env`, variáveis `CEMA_WP_*`), **apenas
   GET**. Use quando o túnel não estiver disponível ou para algo exposto só pela API —
   até agora, todos os importadores (`cema:importar-*`) usam somente a conexão `legado`.

🚫 **REGRAS DURAS:**
- **Nunca** escrever no WordPress vivo nem no banco legado — só leitura
  (GET na REST; SELECT no banco). Nada de POST/PUT/DELETE/UPDATE/INSERT/DDL.
- A conexão `legado` **jamais** roda migrations/seeders nem usa o usuário root.
- O site novo nasce e cresce **no localhost** até o deploy.
- `cemanet.org` (sem `.br`) é outra organização — ignorar.

## Banco de dados

- MySQL **somente** por **migrations + seeders**. NUNCA alterar schema na mão.
- 🚫 **PROIBIDO destruir o banco de dev (conexão padrão):** nunca `migrate:fresh`,
  `migrate:refresh`, `db:wipe`, `migrate:reset`, nem seed/factory destrutivo. Eles apagam
  **TODOS** os dados — inclusive os importados do legado (123 palestras, 44 posts, mídia).
  Só `php artisan migrate` **incremental**. **Todo brief de subagente que rode artisan no banco
  DEVE proibir explicitamente esses comandos.** (Incidente 28/06/2026: um `migrate:fresh` de um
  subagente zerou o dev; recuperável só por ser dev + importação idempotente.)
- Antes de criar tabela/coluna, **conferir o que já existe** (evitar duplicar).
- Chaves estrangeiras sempre. As relações do site atual viram pivôs:
  **107** (palestra→palestrante, 1–2, obrigatório) e **108** (palestra→diretor,
  0–1, opcional) → pivot `palestra_pessoa` com coluna `papel`; cardinalidade
  validada na aplicação (`App\Support\Palestras\CardinalidadePalestra`, chamada
  pelas páginas do Filament Resource de Palestra).
- Importação **idempotente** (upsert por slug), resolvendo palestrantes/diretores
  e taxonomia por slug, **a partir do banco `legado`** (fonte preferida — conteúdo
  mais rico). Ver mapeamento em [DATA-MODEL.md](DATA-MODEL.md) e [DB-LEGADO.md](DB-LEGADO.md).

## Imagens (padrão ÚNICO — WebP)

Qualquer imagem do site é servida em **WebP** e o disco guarda **só WebP** (o original é
capado ≤2000px — ≤1200 na coleção `og` — e reencodado para WebP no upload). **Não** reinvente
upload/otimização por módulo. **Receita copy-paste: [IMAGENS.md](IMAGENS.md).** Referência: `Palestrante`.

- **Imagem de entidade** (palestrante, evento, página institucional…): model
  `implements HasMedia` + `use InteractsWithMedia, RegistraImagensPadrao;` e
  `registrarColecaoImagem('capa'|'galeria', ...)`; Filament usa
  `App\Filament\Support\ComponentesImagem::upload($campo, $colecao, $multiplas)`.
- **Servir**: sempre a conversão, via accessor `getFirstMediaUrl($colecao, 'web'|'thumb')` —
  **nunca** o original. Fallback (iniciais/gradiente) quando não há foto.
- **Imagem no corpo de rich text** (blog): pool central `Biblioteca`, servida por
  `/midia/{id}/web` (`MidiaController`) — não este pipeline.
- O pipeline WebP é **global** (listener `App\Listeners\CaparOriginalDaMidia`); não crie
  disco/otimização/observer próprios para imagem.

## Como trabalhar (workflow)

1. **Planejar antes de codar** — roadmap curto por tarefa (correções triviais
   dispensam).
2. **Migrations/seeders** para qualquer mudança de dados.
3. **Verificar de verdade** antes de declarar pronto:
   `docker compose exec -T app php artisan test` (o projeto **não** usa Laravel
   Sail) + abrir a página no `localhost` e conferir o comportamento real.
4. **Commits atômicos** e descritivos; trabalhar em branch a partir de `main`;
   **semver** em releases.
5. **Mobile-first** e responsivo (desktop/tablet/mobile); **acessibilidade (A11y)**
   e **SEO** desde a estrutura; **orçamento de performance** (HTML enxuto,
   lazy-load, cache) — o WP atual gasta ~0,5 MB de HTML/página; ficar bem abaixo.
6. **Segredos só no `.env`** (gitignored). Nada de credencial versionada.
7. **Segurança**: validação de entrada, CSRF, rate limiting nos formulários,
   auth/roles para a área de membros.
8. **Cabeçalho de autoria** em módulos novos relevantes:
   `Thiago Mourão — https://github.com/MouraoBSB — <data>`.

## Documentos que mantêm o projeto "ciente"

- `PROJECT.md` — visão, escopo, decisões e inventário de conteúdo.
- `ROADMAP.md` — fases e estado atual (atualizar ao concluir etapas).
- `DATA-MODEL.md` — tabelas, relações, regras de negócio e mapeamento WP→MySQL.
- `DB-LEGADO.md` — acesso somente leitura ao banco do WordPress atual (estrutura real).
- `IMAGENS.md` — padrão único de imagens (WebP): receita de model/Filament/serviço.
- `design-system/` — tokens, componentes e mapa de páginas (referência de UI).

## Conflitos e dúvidas

Em conflito de regras, escolher a opção mais segura e conservadora. Em dúvida,
interromper e perguntar antes de prosseguir.
