# Design — Autenticação pública de membro (login + cadastro)

Data: 2026-07-03 · Stack: Laravel 13 · Fortify · Socialite · Blade + Tailwind v4 · MySQL 8 · Docker.
Referências: `config/auth.php`, `config/hashing.php` (driver `cema` + `rehash_on_login`), `routes/web.php`,
`resources/css/app.css` (tokens CEMA), `app/Models/User.php`, `DATA-MODEL.md` (módulo Usuários).

## Objetivo

Dar ao front público um sistema de **login, logout, cadastro aberto e "esqueci a senha"**, por
**e-mail/senha** e por **Google**, na identidade CEMA. Hoje só existe o login do Filament (admin).
Os ~145 membros migrados e novos visitantes precisam entrar/se cadastrar.

## Pré-requisito

Parte da `main` com o **PR #7 mergeado** (back-end de auth: `User` com `HasRoles`, driver de hash
`cema` + `rehash_on_login=true`, papel `frequentador`, `perfis_membro`). A branch `fase-auth` traz o
back-end via `git merge main` após o merge do PR #7; o código só começa depois disso.

## Escopo e fatiamento

- **Esta fatia:** dependências (Fortify + Socialite) + rotas pt-BR + fluxos (login/cadastro/reset/Google)
  + coluna `google_id` + views próprias CEMA (layout de auth enxuto) + segurança (rate-limit, CSRF,
  mensagens genéricas, bloqueio `ativo=false`) + testes.
- **Deferido (não construir agora):** "Minha Conta" (perfil/editar senha — próxima fatia, handoff em
  `handoff_minha_conta/`), 2FA, verificação de e-mail.

## Decisões (alinhadas no brainstorming)

1. **Fortify headless + Socialite + forms Blade/POST.** Fortify traz rotas/actions + rate-limit + reset
   testados e usa `Auth::attempt` (hasher `cema`/rehash de graça). Manual foi descartado (reescreveria
   segurança sensível). Livewire descartado nos forms (agrega pouco em auth, não casa com o POST do Fortify).
2. **Layout de auth enxuto** (card centrado, logo, sem mega-menu), na paleta/tipografia CEMA.
3. **Paths pt-BR**, **nomes de rota do Fortify preservados** (ver §Rotas).
4. **Sem verificação de e-mail** (decisão do cliente): não usar `MustVerifyEmail` nem o feature
   `emailVerification`. *Trade-off consciente registrado:* com cadastro aberto e sem verificação, o
   e-mail não é identificador provado; alguém poderia pré-criar `vitima@gmail.com` e a vítima cair nessa
   conta ao entrar pelo Google. Risco **baixo/aceitável** aqui (papel `frequentador`, só dados do próprio
   perfil, sem transação) — revisitar se entrar algo sensível.

## Arquitetura

- `laravel/fortify` (headless) — **`config/fortify.php` com `'routes' => false`**: as rotas de auth são
  declaradas no próprio `web.php` (ordem garantida, acima do fallback).
- `laravel/socialite` — provider Google.
- `App\Providers\FortifyServiceProvider`: registra as views (`Fortify::loginView`…), a action
  `CreateNewUser`, o `authenticateUsing` customizado e os rate-limiters.
- Features do Fortify: **apenas `registration` + `resetPasswords`**.
- Forms **Blade + POST**; layout `<x-layout.auth>`.

## Rotas (todas no `web.php`, antes do fallback)

| Método | Path (pt-BR) | Nome (preservado) | Alvo |
|---|---|---|---|
| GET/POST | `/entrar` | `login` | Fortify login view / AttemptToAuthenticate |
| POST | `/sair` | `logout` | Fortify logout |
| GET/POST | `/cadastro` | `register` | Fortify register view / CreateNewUser |
| GET/POST | `/esqueci-a-senha` | `password.request` | Fortify forgot-password |
| GET/POST | `/redefinir-senha` | `password.reset` | Fortify reset-password |
| GET | `/auth/google` | `google.redirect` | `GoogleController@redirect` |
| GET | `/auth/google/callback` | `google.callback` | `GoogleController@callback` |

- **Nomes** iguais aos do Fortify (`login`, `register`, `password.request`, `password.reset`, `logout`) —
  o broker/notificação de reset e os redirects resolvem **pelo nome**, não pela URL.
- **`/auth/google/callback` NÃO é traduzido** (já cadastrado assim no Google Console).
- ⚙️ **Ordem determinística:** o catch-all `Route::get('/{slug}', …)` do `web.php` vira
  **`Route::fallback(…)`** — o Laravel sempre o avalia por último, blindando auth/Socialite/Minha Conta
  de sombreamento, independentemente da ordem de registro.

## Fluxos

### Login — `Fortify::authenticateUsing()` customizado
Valida via `Hash::check($senha, $user->password)` (hasher `cema` → aceita `$wp$`/`$P$`). Regras:
- Credenciais inválidas (usuário inexistente **ou** senha errada) → retorna `null` → **mensagem genérica**
  (não revela se o e-mail existe).
- Senha correta **e** `ativo === false` → lança `ValidationException` com mensagem **específica**
  ("Sua conta está inativa. Fale com a secretaria da casa."). Seguro: só aparece a quem já provou a senha.
- Senha correta e ativo → se `Hash::needsRehash($user->password)`, **rehash manual**
  (`$user->forceFill(['password' => Hash::make($senha)])->save()`) — preserva a modernização transparente,
  que o `authenticateUsing` não dispara sozinho. Retorna o `User`.
- Rate-limit (RateLimiter `login`, por e-mail+IP) e **"lembrar de mim"** (checkbox, nativo do Fortify).

### Cadastro — action `App\Actions\Fortify\CreateNewUser`
Valida (nome; e-mail único; senha forte confirmada). Cria o `User`, e **em transação**:
`assignRole('frequentador')` · `email_verified_at = now()` · cria `perfis_membro` (1:1) vazio.
Já autentica. Rate-limit no `POST /cadastro`.

### Google — `App\Http\Controllers\Auth\GoogleController`
- `redirect()` → `Socialite::driver('google')->redirect()`.
- `callback()` → usuário do Google; casa por **e-mail** com `User` existente:
  - existe: se `ativo=false` → bloqueia (mensagem específica); senão grava `google_id` (se vazio) e loga.
  - não existe: cria `frequentador` (e-mail já verificado, `google_id` = `sub`, `perfis_membro` vazio,
    `password = Hash::make(Str::random(64))` — inutilizável mas não-nula; "esqueci a senha" cobre a
    criação de senha local depois). Loga.

### Reset de senha — broker padrão do Laravel
`/esqueci-a-senha` envia link (nome `password.request`); `/redefinir-senha` redefine (`password.reset`).
Em local, **Mailpit** (`localhost:8025`) captura o e-mail. **Não** é verificação de e-mail.

## Schema

Migration nova: `users.google_id` — `string` **nullable**, **unique**. Nada mais muda.

## Views (identidade CEMA, mobile-first, acessível)

- `<x-layout.auth>`: card centrado, logo CEMA, link "voltar ao site", sem mega-menu; tokens do `app.css`.
- Telas: `auth/login`, `auth/register`, `auth/forgot-password`, `auth/reset-password` + botão
  **"Entrar com Google"** em login e cadastro.
- A11y: `<label for>`, foco visível, contraste AA, `aria-invalid`/`aria-describedby` nos erros, mensagens
  de erro associadas ao campo. Mobile-first.

## Configuração

- `config/fortify.php`: `'routes' => false`, `'views' => true`, `'features' => [registration,
  resetPasswords]`, `'home' => '/'` (home enquanto "Minha Conta" não existe), guard `web`.
- `config/services.php`: `google => [client_id, client_secret, redirect]` via
  `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI`.
- Nenhuma alteração no login do **Filament**: admin segue em `/admin/login` + `canAccessPanel`.

## Segurança

CSRF (padrão) · rate-limit em **login, cadastro e reset** · mensagens **genéricas** em falha de credencial
(específica só para `ativo=false` já autenticado) · `ativo=false` bloqueado em login **e** Google ·
`frequentador` é o único papel do auto-cadastro (promoção só no Filament) · **Filament intacto**.

## Plano de testes (feature)

- Login senha **bcrypt nova** → sucesso; **`$wp$`/`$P$` legada** → sucesso **e rehash** (vira `$2y$`).
- `ativo=false` + senha correta → bloqueado com mensagem específica; senha errada → mensagem genérica;
  e-mail inexistente → mesma mensagem genérica (sem enumeração).
- Cadastro → cria `frequentador` + `perfis_membro` + `email_verified_at`; **`Role::findOrCreate('frequentador')`
  no setup** dos testes de cadastro.
- Rate-limit em login e cadastro.
- Google callback: e-mail existente → loga e grava `google_id`; e-mail novo → cria `frequentador` +
  `google_id` + perfil; `ativo=false` → bloqueado.
- Reset: envia link (assert Notification) e redefine a senha.
- **Regressão de rotas:** `/entrar`, `/cadastro`, `/auth/google/callback` resolvem; o redirect **301** de
  slug de post no root (`/{slug}` → `/sementeira/{slug}`) **continua funcionando** via `Route::fallback`.
- `/admin` inalterado: `canAccessPanel` gateia; login do Filament separado do fluxo público.

## Critério de pronto

Visitante se cadastra (vira `frequentador`, com perfil) e já entra; membro migrado loga com a senha antiga
(rehash transparente) ou pelo Google; "esqueci a senha" funciona (Mailpit); `ativo=false` é barrado;
rate-limit ativo; identidade CEMA responsiva/acessível; Filament intacto; suíte verde e Pint limpo.
