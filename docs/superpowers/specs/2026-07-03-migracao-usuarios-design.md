# Design — Migração de Usuários (Área de membros, Fatia 1: dados + admin)

Data: 2026-07-03 · Stack: Laravel 13 · Filament 5 · MySQL 8 · Blade/Livewire 4 · Docker.
Referências: `DATA-MODEL.md` (seção "Módulo Usuários e Área de membros"), `DB-LEGADO.md`
(seção "Usuários no legado"), `ROADMAP.md`, memória do projeto (decisões do dono).

## Objetivo

Trazer os usuários reais do WordPress legado (~145 não-admin) para o site novo, **classificados**
nas dimensões da casa (papel, lotação, atributos) e **mantendo suas senhas** (sem reset), e
disponibilizá-los para **gestão no admin** (Filament). É a fatia banco → importação → admin do
módulo "Área de membros". O login público e a segmentação de conteúdo ficam para fatias seguintes.

## Escopo e fatiamento

- **Fatia 1 (este spec):** dependências (Spatie) + schema (estrutura org + perfil + pivôs + RBAC) +
  seeders de estrutura + hasher de senha legada + importador idempotente + Resources Filament
  (Usuário, Departamento, Setor, Cargo) + gate do painel por papel.
- **Deferidos (registrados, não construídos nesta fatia):**
  - **Audiência de conteúdo** (tabela `audiencias` + visibilidade por interseção) — só faz sentido
    quando houver conteúdo restrito (fase de mensagens mediúnicas).
  - **Permissões finas + Policies de escopo por departamento** + **`filament-shield`** — entram
    com a fatia de permissões/conteúdo restrito. Nesta fatia o RBAC é só os 4 papéis + gate.
  - **Fotos de perfil** — a coluna existe; o importador **não** baixa foto agora (baixa prioridade).
    Fallback futuro: `_foto_de_perfil` → `wp_user_avatar` → Google → Gravatar → iniciais.
  - **Login público / área de membros** (dashboard do frequentador, favoritar palestras, informes).

## Decisões (alinhadas no brainstorming)

1. **Abordagem A — RBAC enxuto + estrutura de dados completa.** Migra tudo (estrutura + perfil +
   pivôs + senhas), mas o RBAC fica no essencial (4 papéis + gate do painel). Sem construir
   autorização fina antes de existir conteúdo a proteger (YAGNI).
2. **Papel ≠ cargo ≠ função.** Três conceitos separados, validado pelos dados reais:
   - **Papel** (auth): `frequentador < trabalhador < diretor < administrador` — Spatie, coluna `nivel`.
   - **Cargo** (domínio): direção (Diretor do DEPAE, Tesoureiro, Conselho…) — catálogo + pivô N:N.
   - **Função no setor**: `membro`/`coordenador` — enum no pivô `setor_usuario`.
3. **Fonte:** banco `legado` (read-only, túnel SSH) — mesma infra `App\Importacao` das outras fatias.
4. **Senhas migram sem reset** (rehash transparente no 1º login). Ver §7.
5. **Identidade = e-mail** (0 vazios, 0 duplicados no legado). `user_login` do WP **descartado** como
   credencial (guardado só como `origem_legado_id`/rastreio).
6. **Admins não migram** (4 contas técnicas); **`subscriber` não migra** (1, lixo — logar p/ revisão).
7. **E-mail verificado automático:** usuário migrado entra com `email_verified_at` preenchido
   (vieram de contas ativas) — evita onda de e-mails de verificação no go-live.
8. **Coordenação:** toda etiqueta `Coordenador de X` colapsa no **setor-base X** + `funcao=coordenador`;
   `X` sozinho → `funcao=membro`. **Nunca** criar um setor chamado "Coordenador de…".
9. **Gate do painel:** `canAccessPanel` passa a exigir papel **diretor** ou **administrador**
   (hoje libera `local/testing`). Frequentador/trabalhador **não** entram no `/admin`.
10. **Atribuição de papel:** `Select` simples no User Resource (sem `filament-shield`).

## Introspecção do legado (2026-07-03, conexão `legado` read-only)

Cardinalidades **confirmadas contra os dados reais** (guard-rail):

| Pergunta | Realidade | Decisão de schema |
|---|---|---|
| Usuário em quantos setores? | **0 a 5** (21×1, 25×2, 10×3, 5×4, 1×5) | pivô **N:N** `setor_usuario` |
| Cargo é do usuário ou do setor? | do **usuário** (meta serializada), **0 a 3** por pessoa | pivô **N:N** `cargo_usuario` |
| Departamento ↔ setor? | 1 setor → 0 ou 1 departamento | **1:N** (`setores.departamento_id` nullable) |
| Papel único por usuário? | **sim** (148×1 papel; **1 usuário com 2**) | precedência: maior `nivel` vence |
| Diretor tem cargo de direção? | **26 diretores, 0 sem cargo** | invariante confirmada |
| "Coordenador" é cargo? | **não** — só aparece como *setor* | vira `funcao=coordenador` no pivô |

**Papéis reais (`wp_capabilities`):** frequentador 69, trabalhador 50, diretor 26, administrator 4
(não migra), subscriber 1 (não migra). **Senhas:** `phpass ($P$)` **75** + `wp_bcrypt ($wp$)` **74**,
zero outro esquema. **E-mails:** 0 vazios, 0 duplicados. **Cobertura de perfil** (detalhe em
`DB-LEGADO.md`): whatsapp 73%, nascimento 70%, endereço 59% (texto único), cursos 7%.

## Schema (migrations incrementais — proibido `migrate:fresh`/`refresh`/`wipe` no dev)

**RBAC (Spatie):** `roles` (**+ coluna `nivel` int**), `permissions`, `model_has_roles`,
`model_has_permissions`, `role_has_permissions` (via `vendor:publish`).

**Catálogos:**
- `departamentos`: `sigla` unique, `nome`, `slug` unique, `descricao?`, `ativo`, `ordem`
- `setores`: `nome`, `slug` unique, `departamento_id?` (FK nullable → PAMANA), `provisorio` (bool), `ativo`
- `cargos`: `nome`, `slug` unique, `departamento_id?` (FK nullable), `institucional` (bool), `ativo`
- `atributos`: `nome`, `slug` unique, `descricao?`

**Usuário/perfil:**
- `users` (+ `origem_legado_id` unsigned unique nullable, `socio` bool default false **indexado**,
  `ativo` bool default true; `email_verified_at` preenchido na importação)
- `perfis_membro` (1:1): `user_id` unique FK, `whatsapp?`, `whatsapp_publico` bool,
  `data_nascimento?`, `endereco?` (text, sem parser), `foto_perfil?` (**não preenchida nesta fatia**)
- `cursos_realizados` (1:N): `user_id` FK, `nome`, `ano?` (smallint), `local?`, `ordem`

**Pivôs:**
- `setor_usuario`: PK `(setor_id, user_id)`, `funcao` enum(`membro`,`coordenador`) default `membro`, `desde?`
- `cargo_usuario`: PK `(cargo_id, user_id)`
- `atributo_usuario`: PK `(atributo_id, user_id)`, `desde?`, `ate?`

## De-para (contra os dados reais; seedado antes da importação)

**Papéis** (nivel): frequentador 10 · trabalhador 20 · diretor 30 · administrador 100.
Mapa WP→CEMA: `frequentador→frequentador`, `trabalhador→trabalhador`, `diretor→diretor`;
`administrator`/`subscriber` **não migram**; usuário com 2 papéis → **maior nível vence**.

**Departamentos (8):** DAS (Assistência Social), DDA (Divulgação e Artes), DED (Estudos
Doutrinários), DEMAPA (Manutenção Patrimonial), DEPAE (Assistência Espiritual), DEPRO (Promoções e
Eventos), DIJ (Infância e Juventude), DECOM (Comunicação e Multimídia).

**Setores (17 slugs → setor-base · departamento · função):**

| slug legado | setor-base | depto | função |
|---|---|---|---|
| `atendimento_fraterno` | Atendimento Fraterno | DEPAE | membro |
| `medium` | Médium | DEPAE | membro |
| `passista_passe_magnetico` | Passe Magnético | DEPAE | membro |
| `harmonizacao` | Harmonização | DECOM | membro |
| `brecho` | Brechó | DEPRO | membro |
| `corte_de_verdurasopa` | Corte de Verduras / Sopa | DAS | membro |
| `recepcionista` | Recepção | DAS | membro |
| `caravaneiro_de_auta_de_souza` | Campanha Auta de Souza | DDA | membro |
| `coordenador_da_campanha_auta_de_souza` | Campanha Auta de Souza | DDA | **coordenador** |
| `coralista_do_cemad` | Coral CEMAD | DDA | membro |
| `teluzes` | TELUZES (Teatro) | DDA | membro |
| `coolaborador_decom` | Colaboração DECOM | DECOM | membro |
| `evangelizador_da_infancia` | Evangelização da Infância | DIJ | membro |
| `evangelizador_da_mocidade` | Evangelização da Mocidade | DIJ | membro |
| `evangelizador_do_ded` | Evangelização (DED) | DED | membro |
| `livraria` | Livraria | DED | membro |
| `pamana` | PAMANA | — (nenhum) | membro |

Regra de coordenação (decisão 8): o slug `coordenador_da_campanha_auta_de_souza` **não** vira setor
próprio — colapsa em "Campanha Auta de Souza" com `funcao=coordenador`. A mesma regra vale para
qualquer futura etiqueta "Coordenador de X". *(Nota de revisão: `caravaneiro` e `coordenador` da
campanha caem no mesmo setor-base "Campanha Auta de Souza"; se quiser preservar "Caravaneiro" como
setor distinto, ajustar aqui.)*

**Cargos (12 slugs → cargo · departamento · institucional):**

| slug legado | cargo | depto | institucional |
|---|---|---|---|
| `diretor_dda` | Diretor do DDA | DDA | não |
| `diretor_ded` | Diretor do DED | DED | não |
| `diretor_decom` | Diretor do DECOM | DECOM | não |
| `diretor_demapa` | Diretor do DEMAPA | DEMAPA | não |
| `diretor_depae` | Diretor do DEPAE | DEPAE | não |
| `diretor_depro` | Diretor do DEPRO | DEPRO | não |
| `diretor_dij` | Diretor do DIJ | DIJ | não |
| `diretor_presidente` | Presidente | — | sim |
| `conselho_diretor` | Conselho Diretor | — | sim |
| `conselho_fiscal` | Conselho Fiscal | — | sim |
| `secretario` | Secretário | — | sim |
| `tesoureiro` | Tesoureiro | — | sim |

Não há `diretor_das` nem vice-presidente no legado. Seedar o cargo "Diretor do DAS" (catálogo
completo, sem ocupante) para consistência; o importador só vincula os que existem.

**Atributos:** `socio` (Sócio).

## Estratégia de senha (o coração da migração)

Os hashes vêm em 2 formatos, ambos incompatíveis com o `password_verify` puro do Laravel:
- `$wp$…` = `"$wp"` + bcrypt `$2y$`, com pré-hash `base64(hmac-sha384(senha, 'wp-sha384'))`.
- `$P$…` = phpass (`crypt_private`, MD5 iterado, salt de 8 chars).

**Plano (já provado contra hash real do `usuario-teste` e por round-trip do phpass):**
1. Importar os hashes **como estão** para `users.password`.
2. `App\Auth\HasherLegadoCema` (estende `Illuminate\Hashing\BcryptHasher`):
   - `check()` reconhece `$wp` (pré-hash + `password_verify(substr($h,3))`), `$P$`/`$H$` (phpass),
     e delega bcrypt/argon nativo ao pai.
   - `needsRehash()` → `true` para `$wp`/`$P$` (força modernização).
3. Registrar como driver `hash` custom. No 1º login válido, `rehashPasswordIfRequired` do Laravel
   regrava em bcrypt nativo — **transparente, sem reset, sem e-mail em massa**.
4. **Idempotência:** nunca sobrescrever hash já modernizado (só grava senha no create ou se ainda
   for hash legado).

Se uma reimportação futura encontrar esquema diferente de `$wp$`/`$P$`, o transformador **loga e não
migra** aquele usuário (fail-closed), sem inventar hash.

## Estrutura de importação (espelha `App\Importacao`)

- `LeitorUsuarios` (interface) + `LeitorUsuariosMysql` (SELECT no legado; fake nos testes)
- `TransformadorUsuarios`: sanitiza nome → **Title Case** (mb, preposições `de/da/do/das/dos/e`
  minúsculas; fonte `display_name`); normaliza flags em **3 estados** (verdadeiro/falso/ausente);
  desserializa `locais_de_trabalho_*`; resolve slug → setor/cargo/depto pelo de-para; aplica a regra
  de coordenação; escolhe o papel (precedência por nível).
- `ImportadorUsuarios`: upsert idempotente **em transação por usuário**, com coletor de avisos.
  Chave: `origem_legado_id` → e-mail. Pivôs por `sync`; `cursos_realizados` por delete+recriação
  ordenada. **Exclui** administrators; **loga** subscriber e qualquer slug/e-mail não resolvido
  (nunca cria às cegas).
- Comando `cema:importar-usuarios` (valida conexão `legado`; imprime resumo + avisos).
- **Seeders** rodam antes (papéis, departamentos, setores, cargos, atributo sócio) — idempotentes por slug.

## RBAC + gate do painel

- 4 papéis Spatie com `nivel`. Nesta fatia, sem permissões finas (catálogo mínimo).
- `User implements FilamentUser`; `canAccessPanel` → `hasAnyRole(['administrador','diretor'])`
  (mantém liberação em `testing`). Relações no `User`: `perfil` (1:1), `setores`/`cargos`/`atributos`
  (N:N), `cursos` (1:N), além de `roles` (Spatie).
- Um seeder cria o **administrador** do site novo (não vem do legado).

## Filament Resources (CRUD)

- **UsuarioResource:** lista (nome, e-mail, papel, sócio, setores); form com `Select` de papel,
  `Select` múltiplo de setores (com função) e cargos, toggle sócio, campos de perfil. Sem gestão de
  permissões finas (deferida).
- **DepartamentoResource / SetorResource / CargoResource:** CRUD dos catálogos. SetorResource permite
  "Sem departamento (PAMANA)"; CargoResource esconde departamento quando `institucional`.

## PII e segurança

- Legado **somente `SELECT`**; **nunca** commitar dump nem PII. Os ~145 registros (nome, e-mail,
  hash, whatsapp, endereço) vivem só no banco de dev.
- Migrações **incrementais**; jamais `migrate:fresh/refresh/wipe/reset` (apagariam os dados já
  importados — palestras, blog, agenda). Todo brief de subagente deve repetir essa proibição.
- `email_verified_at` preenchido na importação (contas ativas).

## Plano de testes

- **Idempotência:** rodar o importador 2× → contagens estáveis, sem duplicar pivôs.
- **Hasher:** `$wp$` e `$P$` aceitam a senha certa e rejeitam a errada; `needsRehash` true p/ ambos;
  bcrypt nativo passa direto.
- **Sanitização de nome:** `ANA KARLA DA SILVA` → `Ana Karla da Silva`; preposições minúsculas.
- **Resolução:** slug → setor/cargo/depto correto; regra de coordenação (`coordenador_…` →
  setor-base + `funcao=coordenador`).
- **Papel:** precedência no caso de 2 papéis; administrators excluídos; subscriber logado e não criado.
- **Gate:** diretor/admin acessam o painel; frequentador/trabalhador não.
- Leitor real (`LeitorUsuariosMysql`): conferir o SQL contra o legado antes do merge (só há fake nos
  testes — lição registrada na memória do projeto).

## Critério de pronto

Rodar `cema:importar-usuarios` traz os ~145 usuários (papel, setores com função, cargos, sócio,
perfil, senha) de forma idempotente; um usuário do legado consegue logar com a senha antiga (rehash
transparente); admin/diretor gerenciam usuários e catálogos no `/admin`; frequentador/trabalhador não
acessam o painel; suíte verde e Pint limpo.
