# Migração das fotos de perfil de usuário — Design

**Data:** 2026-07-04 · **Fatia:** Fotos de perfil (legado → Media Library) + avatar do Google · **Branch:** `fase-fotos-perfil`

## Objetivo

Dar foto de perfil aos membros. Hoje o `ImportadorUsuarios` migra tudo do usuário
(identidade, papéis, lotação, whatsapp, nascimento, endereço, cursos) **menos a
foto** — era o "passo opcional" adiado. Esta fatia cobre duas frentes complementares:

- **A) Migração das fotos locais** que o membro subiu no site atual → Media Library.
- **B) Avatar do Google** capturado no login, para quem entra por Google e ainda
  não tem foto.

Quem não tiver foto por nenhum caminho continua nas **iniciais** (fallback da app,
já pronto).

## Contexto (o que existe)

- `App\Importacao\BaixadorImagem` — baixa uma **URL** para o disco `public`
  (`baixarPara($url, $pasta, $nome)`, idempotente por caminho; retorna `null` em
  URL vazia/HTTP falho/exceção). Já usado pelo import de palestrantes.
- `App\Models\PerfilMembro` — `implements HasMedia`, coleção `foto`
  (`COLECAO_FOTO`), singleFile, com conversões WebP `web` (≤640px) + `thumb`
  (400×400 quadrado) via trait `RegistraImagensPadrao`; accessors
  `foto_url`/`foto_thumb_url`.
- `App\Importacao\LeitorUsuariosMysql.usuarios()` — lê `users` + `usermeta` do
  banco `legado`, mas **não** lê nenhuma meta de foto hoje. `LeitorLegadoMysql`
  resolve `_thumbnail_id → wp_posts.guid` (padrão a reusar para attachment id).
- `App\Http\Controllers\Auth\GoogleController` — cria o usuário + perfil no login
  Google, mas **não** captura `$g->getAvatar()`.

### Fontes de foto no legado (DB-LEGADO.md) — cobertura baixa
| Meta | Cobertura | Formato |
|---|---|---|
| `_foto_de_perfil` | 3% | serializado `{id, url}` (foto de perfil custom do CEMA) |
| `wp_user_avatar` | 14% | attachment id (avatar genérico) |

`nsl_user_avatar_md5` (avatar social) e Gravatar ficam **fora** (decisão do dono).

## Decisões (fixadas no brainstorming)

1. **Fontes: só as fotos locais** — `_foto_de_perfil` + `wp_user_avatar`. Sem
   Gravatar nem avatar social.
2. **Candidatas em ordem** — o leitor devolve uma **lista ordenada** de URLs
   candidatas por usuário; o importador tenta na sequência até uma baixar (cobre
   o caso raro da foto prioritária com link quebrado). Ordem:
   `_foto_de_perfil`.url → `_foto_de_perfil`.id→guid → `wp_user_avatar`.id→guid.
3. **Comando separado, best-effort, re-executável** — `cema:importar-fotos-usuarios`,
   desacoplado do `cema:importar-usuarios` (baixar imagem é lento/falível; uma
   falha de rede não pode derrubar o import de dados).
4. **Avatar do Google no login** — capturado em **fila**, quando o membro não tem
   foto.
5. **Nunca sobrescrever foto existente** (regra transversal — ver abaixo).

## Arquitetura

Duas partes, reusando `BaixadorImagem` + a coleção `foto` do `PerfilMembro`:

- **A** estende o leitor (`fotos_urls` por usuário) + um importador dedicado
  (`ImportadorFotosUsuarios`) chamado por um comando novo.
- **B** um job em fila (`CapturarAvatarGoogleJob`) disparado pelo `GoogleController`.

## Parte A — migração das fotos locais

### Leitor
`LeitorUsuariosMysql.usuarios()` passa a incluir, em cada usuário, a chave
**`fotos_urls`**: `array<string>` de URLs candidatas em ordem de prioridade,
já deduplicada e sem vazias. Resolução:

1. `_foto_de_perfil` → `unserialize(..., ['allowed_classes' => false])`; se for
   array: (a) `url` (se não-vazia); (b) resolve `id` → `wp_posts.guid` (se `id`).
2. `wp_user_avatar` (attachment id) → `wp_posts.guid`.

O `LeitorUsuariosFake` aceita `fotos_urls` nos itens injetados (para os testes).
O `ImportadorUsuarios` (import de dados) **ignora** essa chave — nada muda nele.

> A resolução attachment→guid reusa o padrão do `LeitorLegadoMysql`
> (`SELECT guid FROM wp_posts WHERE ID = ?`).

### Importador + comando
- `App\Importacao\ImportadorFotosUsuarios` — itera `leitor->usuarios()`; para cada
  usuário:
  1. Acha o `User` local por `origem_legado_id` (pula se não existe — ex.: admin/
     hash ignorado no import de dados).
  2. Pega/cria o `PerfilMembro`. **Se já houver foto na coleção `foto`, pula**
     (guard transversal).
  3. Tenta cada URL de `fotos_urls` em ordem via `BaixadorImagem::baixarPara(...,
     'usuarios', (string)$origemId)` até uma retornar caminho; então
     `addMedia(...)->toMediaCollection(PerfilMembro::COLECAO_FOTO)`.
  4. Contabiliza: anexadas / puladas (já tinham foto) / sem-candidata / falhas
     (todas as URLs falharam) — retorna resumo, best-effort (falha vira aviso,
     segue).
- `App\Console\Commands\ImportarFotosUsuarios` (`cema:importar-fotos-usuarios`) —
  injeta o leitor + importador, imprime o resumo. Idempotente e re-executável.

## Parte B — avatar do Google no login

- `App\Jobs\CapturarAvatarGoogleJob implements ShouldQueue` — `(int $userId,
  string $avatarUrl)`. No `handle()`: acha `User` + `PerfilMembro`; **se já houver
  foto, retorna**; senão baixa `$avatarUrl` via `BaixadorImagem` → coleção `foto`.
  Falha vira log (best-effort). (Segurança contra corrida: revalida "sem foto"
  dentro do job.)
- `GoogleController.callback()` — depois de resolver o usuário (novo ou existente)
  e antes/depois do login, **se o perfil não tem foto e `$g->getAvatar()` existe**,
  despacha `CapturarAvatarGoogleJob`. Em fila, não bloqueia o redirect do login.

## Regra transversal — nunca sobrescrever

Em **todos** os caminhos (migração, Google, re-execução), a foto só é setada **se
o perfil não tiver nenhuma mídia na coleção `foto`**. Consequências:

- Foto que o membro subiu no **Minha Conta sempre vence**.
- Re-rodar a migração é seguro (não duplica, não troca).
- Entre legado e Google, quem chegar primeiro fica (migração roda em lote; Google,
  no login).
- Conversões WebP (`web` 640 / `thumb` 400 quadrado) saem automáticas do
  `PerfilMembro` no `addMedia`.

## Testes

- **Migração** (`ImportadorFotosUsuarios`, com `LeitorUsuariosFake` + `Http::fake`):
  - usuário com `fotos_urls` e perfil sem foto → mídia anexada na coleção `foto`;
  - **idempotência**: 2ª rodada não re-anexa (perfil já tem foto);
  - **guard**: perfil com foto pré-existente não é tocado;
  - **ordem/fallback**: 1ª URL falha (HTTP 404) → baixa a 2ª;
  - usuário sem `fotos_urls` (ou sem `User` local) → nenhuma mídia, sem erro.
- **Google** (`CapturarAvatarGoogleJob` + fluxo): mock do Socialite ganha
  `getAvatar`;
  - job anexa quando o perfil não tem foto;
  - **guard**: perfil com foto não é tocado;
  - o login **enfileira** o job (`Queue::fake` / `Bus::fake`), não roda inline.

## Não-objetivos

- Gravatar e avatar social `nsl_user_avatar_md5` (não migra).
- Sobrescrever/substituir foto que o membro já tem.
- Alterar o `cema:importar-usuarios` (import de dados) além de o leitor passar a
  expor `fotos_urls` (que ele ignora).
- Backfill de avatar do Google para usuários **já** logados no passado (só a partir
  do próximo login).

## Riscos e mitigações

- **`wp_posts.guid` desatualizado/errado** (WP nem sempre mantém a guid como URL
  atual): as candidatas em ordem + best-effort + fallback nas iniciais absorvem;
  o import de palestrantes usa guid e funciona.
- **Download lento/falho**: comando separado (não afeta o import de dados) e job em
  fila (não bloqueia login); ambos best-effort com log.
- **Corrida** (dois caminhos setando ao mesmo tempo): guard "sem foto" revalidado
  no ponto de anexar; singleFile evita duplicata.
