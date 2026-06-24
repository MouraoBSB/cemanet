# CLAUDE.md — Novo site do CEMA (Centro Espírita Maria Madalena)

Leia antes de agir. Visão e escopo: ver [PROJECT.md](PROJECT.md).
Fases e estado atual: ver [ROADMAP.md](ROADMAP.md).
Modelo de dados e regras de negócio: ver [DATA-MODEL.md](DATA-MODEL.md).
Referência visual: pasta [design-system/](design-system/).

## O que é este projeto

Reconstrução do site **cemanet.org.br** (CEMA – Centro Espírita Maria Madalena)
**SEM WordPress**: uma aplicação própria, construída de forma **incremental no
localhost**, começando pela **fatia vertical do módulo "Palestras"**
(banco → admin → páginas públicas → migração de dados) e depois expandindo
para os demais módulos.

O site atual roda em WordPress + Elementor + Jet Engine; este projeto o
substitui por uma base moderna, leve, administrável e mantível.

## Stack (fixa)

- **PHP 8.3 · Laravel 13 · Filament 5 · MySQL 8**
- **Vite + Tailwind** (alimentado pelos tokens em `design-system/tokens.json`)
- **Blade + Livewire** no front público (SSR por padrão → SEO e performance)
- **Docker** no local (MySQL/Mailpit/Adminer via `docker-compose.yml`); produção
  em **VPS Linux + Docker** (local == produção)

## Idioma

Tudo em **português brasileiro**: código (identificadores de domínio),
comentários, mensagens de interface/erro, commits e respostas. A sintaxe da
linguagem e APIs de terceiros ficam no original.

## Acesso ao site atual (SOMENTE LEITURA)

Três insumos já existem; use-os, não recrie:

1. **Design** — `design-system/` (tokens, componentes, mapa de páginas) e, para
   fidelidade pixel a pixel, o repositório do snapshot
   `github.com/MouraoBSB/cemanet.org-wordpress` (pasta `snapshot/`).
2. **Conteúdo (REST)** — a **REST API** de cemanet.org.br (Application Password no
   `.env`, variáveis `CEMA_WP_*`). Usada **apenas com requisições GET**.
3. **Estrutura do banco (legado, ao vivo)** — conexão **`legado` somente leitura**
   ao MySQL do WordPress atual, via **túnel SSH**, com um usuário **só-SELECT**
   (variáveis `LEGADO_DB_*`). Dá o schema real (tabelas, `wp_postmeta`, relações
   Jet `wp_jet_rel_107/108`, taxonomias). Ver **[DB-LEGADO.md](DB-LEGADO.md)**.

🚫 **REGRAS DURAS:**
- **Nunca** escrever no WordPress vivo nem no banco legado — só leitura
  (GET na REST; SELECT no banco). Nada de POST/PUT/DELETE/UPDATE/INSERT/DDL.
- A conexão `legado` **jamais** roda migrations/seeders nem usa o usuário root.
- O site novo nasce e cresce **no localhost** até o deploy.
- `cemanet.org` (sem `.br`) é outra organização — ignorar.

## Banco de dados

- MySQL **somente** por **migrations + seeders**. NUNCA alterar schema na mão.
- Antes de criar tabela/coluna, **conferir o que já existe** (evitar duplicar).
- Chaves estrangeiras sempre. As relações do site atual viram pivôs:
  **107** (palestra→palestrante, 1–2, obrigatório) e **108** (palestra→diretor,
  0–1, opcional) → pivot `palestra_pessoa` com coluna `papel`; cardinalidade
  validada na aplicação (FormRequest/Policy/observer).
- Importação **idempotente** (upsert por slug), resolvendo palestrantes/diretores
  e taxonomia por slug. Ver mapeamento de campos em [DATA-MODEL.md](DATA-MODEL.md).

## Como trabalhar (workflow)

1. **Planejar antes de codar** — roadmap curto por tarefa (correções triviais
   dispensam).
2. **Migrations/seeders** para qualquer mudança de dados.
3. **Verificar de verdade** antes de declarar pronto: `sail artisan test`
   (ou `php artisan test`) + abrir a página no `localhost` e conferir o
   comportamento real.
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
- `DATA-MODEL.md` — tabelas, relações, regras de negócio e mapeamento REST→MySQL.
- `DB-LEGADO.md` — acesso somente leitura ao banco do WordPress atual (estrutura real).
- `design-system/` — tokens, componentes e mapa de páginas (referência de UI).

## Conflitos e dúvidas

Em conflito de regras, escolher a opção mais segura e conservadora. Em dúvida,
interromper e perguntar antes de prosseguir.
