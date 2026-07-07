# Migração das fotos de perfil de usuário — Design

**Data:** 2026-07-04 · **Fatia:** Fotos de perfil (legado → Media Library) + avatar do Google + remover foto/precedência do membro · **Branch:** `fase-fotos-perfil`

## Objetivo

Dar foto de perfil aos membros e deixar o membro no controle da própria foto. Hoje
o `ImportadorUsuarios` migra tudo do usuário **menos a foto**, o `EditarPerfil`
deixa trocar mas **não remover**, e nada auto-popula avatar. Esta fatia cobre:

- **A) Migração das fotos locais** que o membro subiu no site atual → Media Library.
- **B) Avatar do Google** capturado no login (em fila), para quem entra por Google.
- **C) "Remover foto" no Minha Conta** + **precedência do membro** sobre a
  auto-população (flag `foto_definida_pelo_membro`).

Quem não tiver foto por nenhum caminho continua nas **iniciais** (fallback pronto).

## Contexto (o que existe)

- `App\Importacao\BaixadorImagem::baixarCapado($url, $teto)` — valida o esquema
  `http(s)` (bloqueia guid/lixo não-URL, com `Log::warning`), baixa e devolve os
  **bytes em memória** capados ao lado maior `$teto`; `null` em URL inválida/HTTP
  falho/exceção. É o método usado aqui (**não** `baixarPara`): bytes em memória
  eliminam qualquer arquivo de staging — logo, sem colisão de caminho.
- `App\Models\PerfilMembro` — `HasMedia`, coleção `foto` (`COLECAO_FOTO`),
  **singleFile**, conversões WebP `web` (Fit::Max 640) + `thumb` (Fit::Crop
  400×400) via `RegistraImagensPadrao`; accessors `foto_url`/`foto_thumb_url`.
  **As conversões rodam síncronas (`nonQueued`)** — o GD precisa abrir os bytes.
- `App\Importacao\LeitorLegadoMysql::urlDaImagem()` resolve `_thumbnail_id →
  wp_posts.guid` — padrão a reusar para attachment id.
- `App\Importacao\ImportadorBlog` (linhas ~62-99) é o **precedente** de anexar
  imagem por bytes: `$bytes = $baixador->baixarCapado($url, 2000)` +
  `addMediaFromString($bytes)->usingFileName(basename(parse_url($url,
  PHP_URL_PATH) ?? 'foto.jpg'))->toMediaCollection(...)` (o Spatie isola cada
  mídia em seu diretório — `usingFileName` não colide entre usuários).
- `App\Http\Controllers\Auth\GoogleController` — cria usuário + perfil no login,
  mas **não** captura `$g->getAvatar()`.
- `App\Livewire\Conta\EditarPerfil` — edita o perfil; foto via cropper
  (`$wire.upload`) desacoplada; propriedades em whitelist (blindagem).
- Os 4 comandos `cema:importar-*` **checam a conexão `legado`**
  (`instanceof Leitor*Mysql` + `DB::connection('legado')->getPdo()`) com mensagem
  amigável de túnel SSH antes de rodar.

### Fontes de foto no legado (DB-LEGADO.md) — cobertura baixa
| Meta | Cobertura | Formato |
|---|---|---|
| `_foto_de_perfil` | 3% | serializado `{id, url}` (foto custom do CEMA) |
| `wp_user_avatar` | 14% | attachment id (avatar genérico) |

`nsl_user_avatar_md5` e Gravatar ficam **fora** (decisão do dono).

## Decisões (brainstorming + verificação adversarial)

1. **Fontes: só as fotos locais** — `_foto_de_perfil` + `wp_user_avatar`.
2. **Candidatas em ordem** — o leitor devolve `fotos_urls` (lista ordenada); o
   importador tenta na sequência até uma baixar. Ordem: `_foto_de_perfil`.url →
   `_foto_de_perfil`.id→guid → `wp_user_avatar`.id→guid.
3. **Comando separado, best-effort, re-executável** — `cema:importar-fotos-usuarios`.
4. **Avatar do Google no login** — job em fila, quando o membro não tem foto.
5. **Precedência do membro (flag `foto_definida_pelo_membro`)** — o membro pode
   **remover** a foto, e a auto-população **nunca** vence o membro (ver §Regra
   transversal).
6. **Corrida entre migração e Google: tolerada** — os dois caminhos raramente
   coincidem, e o pior caso é "qual foto do mesmo usuário fica" (sem dado sensível,
   sem corrupção). Não vale `lockForUpdate`; guard revalidado no ponto de anexar
   estreita a janela.

## Arquitetura

Três partes, reusando `BaixadorImagem` + a coleção `foto` do `PerfilMembro`, mais
uma **coluna nova** `perfis_membro.foto_definida_pelo_membro` (bool, default false;
migration **incremental**).

## Parte A — migração das fotos locais

### Leitor
`LeitorUsuariosMysql::usuarios()` passa a incluir em cada usuário a chave
**`fotos_urls`**: `array<string>` de URLs candidatas em ordem, deduplicada e sem
vazias. Resolução (padrão defensivo já usado em `itens()`):

1. `@unserialize($meta['_foto_de_perfil'] ?? '', ['allowed_classes' => false])`;
   se `is_array`: (a) `url` (se não-vazia); (b) resolve `id` → `wp_posts.guid`.
2. `wp_user_avatar` (attachment id) → `wp_posts.guid`.

**`@unserialize` inválido, `id` sem `guid` correspondente ou meta ausente = a
candidata simplesmente não entra na lista** (não interrompe a leitura dos demais).
O `LeitorUsuariosFake` aceita `fotos_urls` nos itens injetados. O
`ImportadorUsuarios` (import de dados) **ignora** a chave — nada muda nele.

> A resolução attachment→guid reusa o SQL de `LeitorLegadoMysql::urlDaImagem`
> (`SELECT guid FROM wp_posts WHERE ID = ?`). **Verificar contra o legado real**
> (§Verificação) — é SQL novo em `usermeta`, só coberto por Fake.

### Importador + comando
- `App\Importacao\ImportadorFotosUsuarios::importar(callable $log): array` —
  retorna `array{anexadas:int, puladas:int, sem_candidata:int, falhas:int,
  avisos:string[]}` (mesmo formato dos irmãos, com a chave `avisos`). Para cada
  usuário do leitor:
  1. Acha o `User` local por `origem_legado_id` (pula se não existe — admin/hash
     ignorado no import de dados).
  2. `PerfilMembro::firstOrCreate(['user_id' => $user->id])`.
  3. **Guard transversal**: se `foto_definida_pelo_membro` **ou** a coleção `foto`
     não estiver vazia → pula (conta em `puladas`).
  4. Tenta cada URL de `fotos_urls` em ordem via
     `BaixadorImagem::baixarCapado($url, 2000)` até uma devolver bytes; então
     (padrão do `ImportadorBlog`): `$perfil->addMediaFromString($bytes)
     ->usingFileName(basename(parse_url($url, PHP_URL_PATH) ?? 'foto.jpg'))
     ->toMediaCollection(PerfilMembro::COLECAO_FOTO)`. Todas falharam → `falhas`
     + aviso. Bytes em memória → sem arquivo de staging, sem colisão entre A e B.
- `App\Console\Commands\ImportarFotosUsuarios` (`cema:importar-fotos-usuarios`) —
  **replica o guard de conexão `legado`** dos comandos irmãos (`instanceof
  LeitorUsuariosMysql` + `DB::connection('legado')->getPdo()` com a mensagem de
  túnel SSH) antes de rodar; injeta leitor + importador; itera `avisos` no resumo.
  Idempotente e re-executável.

## Parte B — avatar do Google no login (fila)

- `App\Jobs\CapturarAvatarGoogleJob implements ShouldQueue` — `(int $userId,
  string $avatarUrl)`. No `handle()`:
  1. Acha o `User`; `PerfilMembro::firstOrCreate(['user_id' => $userId])`
     (**não** assume perfil existente).
  2. **Guard transversal**: se `foto_definida_pelo_membro` **ou** a coleção `foto`
     não estiver vazia → retorna.
  3. `$bytes = BaixadorImagem::baixarCapado($avatarUrl, 2000)`; se não-nulo,
     `$perfil->addMediaFromString($bytes)->usingFileName('google-avatar.jpg')
     ->toMediaCollection(PerfilMembro::COLECAO_FOTO)`. Falha → log, sem crash.
- `GoogleController::callback()` — depois de resolver o usuário (novo/existente) e
  logar, **se `$g->getAvatar()` existe e o perfil não tem foto e
  `!foto_definida_pelo_membro`**, despacha `CapturarAvatarGoogleJob`. Em fila, não
  bloqueia o redirect.
- **Retry por login (tolerado):** se o download falhar, o job é re-enfileirado no
  próximo login (o guard só barra quando há foto/flag). Aceito como best-effort —
  avatar do Google é confiável; falha persistente é rara. Sem cooldown (YAGNI).

## Parte C — "Remover foto" + precedência do membro (Minha Conta)

- **Coluna** `perfis_membro.foto_definida_pelo_membro` (bool, default false).
  Setada **só por código controlado** (não é propriedade bindável do Livewire —
  blindagem preservada).
- **`EditarPerfil`**:
  - Botão **"Remover foto"** no card da foto, visível **só quando há foto** na
    coleção. Marca uma remoção **pendente** (`$removerFoto = true`), aplicada no
    **`salvar()`** (Cancelar/sair descarta — estado Livewire). A UI indica "será
    removida ao salvar".
  - No `salvar()` (dentro da transação):
    - se `$removerFoto`: `$perfil->clearMediaCollection(COLECAO_FOTO)` + setar
      `foto_definida_pelo_membro = true`;
    - se um novo `$foto` foi enviado (fluxo existente): anexar + setar
      `foto_definida_pelo_membro = true`.
  - Enviar foto nova e remover são mutuamente exclusivos (enviar foto limpa o
    `$removerFoto`).

## Regra transversal — precedência do membro

A auto-população (Parte A **e** Parte B) só age quando **`foto_definida_pelo_membro
=== false` E a coleção `foto` está vazia**. Consequências:

| flag | coleção `foto` | auto-popula? |
|---|---|---|
| false | vazia | **sim** (migração/Google) |
| false | tem foto | não (idempotente; não troca uma auto-foto) |
| true (membro subiu/removeu) | qualquer | **não** (membro sempre vence) |

- Membro que **removeu** → flag true, coleção vazia → **iniciais grudam** (Google
  não re-adiciona).
- Membro que **subiu** (inclusive antes desta fatia, com flag ainda false) →
  protegido pelo "coleção não-vazia".
- Re-rodar a migração é seguro; conversões WebP saem automáticas no `addMedia`.

## Testes

- **Leitor** (`LeitorUsuariosFakeTest`/novo): `fotos_urls` presente é repassado;
  o `ImportadorUsuarios` de dados segue verde com a chave nova (não a lê).
- **Migração** (`ImportadorFotosUsuarios`): usar um **stub de `BaixadorImagem`**
  (ou `Http::fake` devolvendo **bytes de imagem reais** via
  `UploadedFile::fake()->image(...)->get()`, seguindo o `ImportadorPalestrasTest`)
  — senão as conversões síncronas do Spatie falham e o teste não prova nada.
  Casos: perfil sem foto/flag false → mídia anexada; **idempotência** (2ª rodada
  pula); **guard flag** (flag true não é tocado); **guard coleção** (perfil com
  foto não é tocado); **ordem/fallback** (1ª URL 404 → baixa a 2ª); sem
  `fotos_urls`/sem `User` local → nada, sem erro.
- **Google** (`CapturarAvatarGoogleJob` + fluxo): **atualizar o helper
  `mockGoogle()`** de `GoogleLoginTest.php` para estubar `getAvatar` (param
  opcional `?string $avatarUrl = null`) — senão os 3 testes que chegam ao login
  quebram (`BadMethodCallException`). Casos: login **enfileira** o job
  (`Bus::fake`/`Queue::fake`) quando sem foto+flag false; **não** enfileira quando
  há foto ou flag true; job anexa quando sem foto; job com perfil ausente
  `firstOrCreate` (não crasha).
- **Remover foto** (`EditarPerfilTest`): remover no salvar limpa a coleção → cai
  nas iniciais **e seta o flag**; botão só aparece com foto; enviar foto seta o
  flag; **flag respeitado** — após remover, um `ImportadorFotosUsuarios`/job do
  Google **não** re-popula.

## Verificação (antes do merge)

- Suíte completa verde + Pint (gate padrão).
- **GATE OBRIGATÓRIO, BLOQUEIA O MERGE (reforçado pelo dono): túnel SSH `legado`
  no ar** + conferência manual do SQL novo do `LeitorUsuariosMysql` via `tinker`,
  cobrindo casos reais de `_foto_de_perfil`/`wp_user_avatar`, confirmando o
  **formato do meta_value** (int simples vs. serializado) e os `wp_posts.guid`
  resultantes. Um guid errado **anexaria a foto errada** — por isso é bloqueante e
  não opcional. O SQL em `usermeta` é novo, só coberto por Fake; o formato do
  `wp_user_avatar` **não** foi confirmado ao vivo (túnel fora na verificação). Ver
  memória `verificar-leitor-legado-contra-banco-real`.
- Verificação visual do Minha Conta: botão "Remover foto" aparece/some, remoção
  aplica no salvar, Cancelar descarta.

## Não-objetivos

- Gravatar e avatar social `nsl_user_avatar_md5` (não migra).
- Sobrescrever/substituir foto que o membro já definiu.
- Alterar o `cema:importar-usuarios` além de o leitor expor `fotos_urls` (ignorada).
- Backfill de avatar do Google para logins passados (só a partir do próximo login).
- Cooldown/retry sofisticado do job (best-effort aceito).

## Riscos e mitigações

- **Colisão de nome de arquivo Parte A × Parte B** (achado crítico): eliminada de
  raiz por `baixarCapado()` (bytes em memória, **sem** arquivo de staging no disco)
  + `addMediaFromString()` (o Spatie isola cada mídia no próprio diretório) — não
  há caminho compartilhado onde a foto de um usuário sobreponha a de outro.
- **`wp_posts.guid` desatualizado / formato do meta inesperado**: candidatas em
  ordem + best-effort + fallback nas iniciais absorvem; leitor com parsing
  defensivo; **verificação contra o legado real antes do merge** (§Verificação).
- **`Http::fake` com bytes não-imagem** faria as conversões síncronas do Spatie
  falharem: testes usam bytes de imagem reais / stub do `BaixadorImagem`.
- **`getAvatar()` no mock estrito do Socialite** quebra o `GoogleLoginTest`:
  `mockGoogle()` é atualizado para estubar `getAvatar`.
- **Corrida** migração×Google: tolerada (§Decisão 6) — guard revalidado no ponto
  de anexar; sem `singleFile` "evitando" nada (singleFile **substitui**, não
  protege — a proteção é o guard flag+coleção).
