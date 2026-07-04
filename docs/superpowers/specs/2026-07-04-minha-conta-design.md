# Design — Minha Conta (área do membro / self-service)

Data: 2026-07-04 · Stack: Laravel 13 · Blade (SSR) + Livewire · Tailwind v4 · Spatie Media Library · MySQL 8 · Docker.
Referências: `handoff_minha_conta/` (identidade visual — traduzir, não copiar o `.dc.html`/`support.js`),
`resources/views/components/layout/{header,footer}.blade.php`, `resources/views/components/palestra/linha.blade.php`,
`resources/views/components/ui/particulas.blade.php`, `app/Models/{User,PerfilMembro,Palestra,Palestrante}.php`,
`app/Models/Concerns/RegistraImagensPadrao.php`, `resources/css/app.css` (@theme), `DATA-MODEL.md`.

## Objetivo

A metade **front-end** da gestão de usuários (a metade admin já é o Filament). Área logada onde o membro vê e
edita os próprios dados, substituindo a área logada do WordPress. Passa a ser o **destino do login**.

## Pré-requisito

Parte da `main` com a fatia de **autenticação** (PR #8) e a **migração de usuários** (PR #7) já mescladas:
login/sessão, `User` com `HasRoles` + `perfil()`/`setores()`/`cargos()`/`socio`, tabela `perfis_membro`.

## Escopo e fatiamento

- **Esta fatia:** casca da conta (layout logado) + header global auth-aware (site-wide) + Painel + Meu Perfil
  (visualização + edição) + foto de perfil (Spatie Media) + amarração do pós-login.
- **Deferido (não construir):** módulos futuros de membro — **nada de "em breve"/placeholder** (big-bang). O menu
  da conta tem só **Painel** e **Meu Perfil**.

## Decisões

1. **Blade SSR** para leitura (Painel + Meu Perfil na visualização); **Livewire** só para o formulário de **edição**
   do perfil (preview de upload, toggle de visibilidade, validação inline) — é onde a reatividade agrega.
2. **Reaproveitar** o layout real (header/footer), os componentes existentes (`x-palestra.linha`, `x-ui.particulas`)
   e os tokens do `app.css` (@theme) — traduzir o handoff, **não** forkar estilos nem a paleta.
3. **Header logado (site-wide):** avatar + "Olá, {primeiro nome}" + **dropdown Alpine** (Minha Conta / Sair); o
   off-canvas mobile ganha uma seção "Minha conta". Visitante: links **reais** Entrar/Cadastrar.
4. **Faixa de saudação unificada** como cabeçalho das páginas da conta (Painel **e** Meu Perfil) — sem cover separado
   no perfil (sem foto de capa, um cover seria um 2º herói redundante).
5. **Foto = Spatie Media Library** (consistência com `Palestrante`), via trait `RegistraImagensPadrao`. A coluna
   `foto_perfil` (string, nunca preenchida) é **dropada** (migration incremental + removida do `$fillable`); a Media
   Library a substitui. A coleção `foto` usa `larguraWeb: 640` (o avatar nunca aparece em 1600px); `thumb` 400×400.
6. **`iniciais()` extraído** para trait `Concerns\TemIniciais` (fonte do nome sobrescrevível), aplicada em `User`
   (de `name`) e `Palestrante` (de `nome`) — **saída idêntica à atual** (os testes de `iniciais` do Palestrante
   permanecem verdes).
7. **Atuação/papel/sócio** são **somente-leitura** ("gerido pela casa") — nunca entram como propriedades graváveis
   do componente Livewire.
8. **Cropper client-side (quadrado)** no upload da foto (ex.: Cropper.js): o membro enquadra o rosto antes de enviar,
   e o recorte quadrado evita o corte central ruim do `Fit::Crop`. O JS do cropper carrega **só na página de edição**
   (orçamento de performance), com enhancement progressivo (sem JS, o input de arquivo simples ainda envia).

## Arquitetura

- Grupo de rotas `auth` + prefixo `minha-conta` + nome `conta.`. Fortify `home` → `/minha-conta`.
- Layout novo `x-layout.conta` (header real auth-aware + faixa de saudação + nav da conta + `{{ $slot }}` + footer real).
- Controller `App\Http\Controllers\ContaController` (SSR): `painel()`, `perfil()`.
- Componente Livewire `App\Livewire\Conta\EditarPerfil` (edição do perfil).
- `PerfilMembro implements HasMedia` (trait `RegistraImagensPadrao`, coleção `foto`, accessors `fotoUrl`/`fotoThumbUrl`).

## Rotas

| Método | Path | Nome | Alvo |
|---|---|---|---|
| GET | `/minha-conta` | `conta.painel` | `ContaController@painel` |
| GET | `/minha-conta/perfil` | `conta.perfil` | `ContaController@perfil` |

Grupo `Route::middleware('auth')->prefix('minha-conta')->name('conta.')->group(...)`, declarado **após** o bloco
`guest` e **antes** do conteúdo público; o `Route::fallback` continua por último. `POST /sair` (`logout`) já existe.

## Casca — layout `x-layout.conta`

`<x-layout.header/>` (auth-aware) → **faixa de saudação** (gradiente + `<x-ui.particulas>`, avatar foto/iniciais,
rótulo "Olá," + nome, chip de papel, selo **Sócio** condicional) → **nav da conta** (sidebar sticky no desktop /
chips roláveis no mobile — só Painel e Meu Perfil, `aria-current` no ativo) → `{{ $slot }}` → `<x-layout.footer/>`.

## Header global auth-aware (vale para o site inteiro)

- **Visitante:** `Entrar` (`route('login')`) + `Cadastrar` (`route('register')`) substituem os `<span aria-disabled>`
  atuais (`header.blade.php:27-32`).
- **Logado:** avatar (foto ou iniciais) + "Olá, {primeiro nome}" + dropdown Alpine → Minha Conta (`route('conta.painel')`)
  / Sair (form `POST route('logout')` com `@csrf`).
- **Off-canvas mobile:** seção "Minha conta" (avatar+nome, Minha Conta, Sair) quando logado; Entrar/Cadastrar quando visitante.

## Painel (`conta.painel`, Blade SSR)

- Card de boas-vindas.
- **Próximas palestras:**
  `Palestra::publicado()->whereNotNull('data_da_palestra')->where('data_da_palestra','>=',\Illuminate\Support\Carbon::today())`
  `->with(['palestrantesAtivos','assuntos'])->orderBy('data_da_palestra')->take(4)->get()`.
  `>= today()` (início do dia) mostra a palestra de **hoje** até o fim do dia — o `CalendarioController` usa `now()`;
  alinhar os dois fica **fora do escopo** desta fatia (não mexer no Calendário). Render reusando `<x-palestra.linha>`.
  "Ver todas →" → `route('palestras.calendario')`. Estado vazio discreto quando não houver próximas.
- **Atalhos rápidos:** cards para `palestras.calendario`, `palestras.index`, `blog.index`, `agenda.index`.

## Meu Perfil (`conta.perfil`)

### Visualização (Blade SSR)
Faixa de saudação como header + botão "Editar perfil". Cards:
- **Dados pessoais** (`dl`): Nome público (`User.name`), Data de nascimento (`perfil.data_nascimento`), Endereço
  (`perfil.endereco`) + selo "não é público — apenas administrativo".
- **Contato:** WhatsApp (`perfil.whatsapp`) + selo de visibilidade (`perfil.whatsapp_publico`).
- **Minha atuação no CEMA** (read-only, selo "Gerido pela casa"): setores (`User.setores`, chips, marcando
  `pivot.funcao='coordenador'`), cargos (`User.cargos`), papel/nível (role Spatie), Sócio (`User.socio`). Se
  `frequentador` **sem** setores → linha discreta "Você ainda não atua em um setor da casa"; papel e Sócio aparecem sempre.

### Edição (Livewire `EditarPerfil`)
- **Foto (com cropper):** cropper client-side (ex.: Cropper.js), proporção **quadrada** (1:1) — o membro seleciona o
  arquivo, enquadra o rosto, vê o preview, e o **recorte** (canvas → Blob) vira o upload do Livewire (`WithFileUploads`).
  A imagem sobe já quadrada; as conversões `web`/`thumb` só redimensionam (sem corte central ruim). Fallback nas iniciais
  quando não há foto; **sem** upload de capa. Enhancement progressivo: sem JS, o input de arquivo simples ainda envia (o
  `thumb` central se aplica). Validação: `['image','mimes:jpg,jpeg,png,webp','max:1024']`. O JS do cropper carrega **só
  nesta página** (entrada Vite dedicada ou `@assets` do Livewire), nunca site-wide.
- **Dados pessoais** editáveis: `name` → `User`; `data_nascimento`, `endereco` → `perfil`.
- **Contato:** `whatsapp` + toggle switch `whatsapp_publico` → `perfil`.
- **Minha atuação** renderizada **travada/somente-leitura** (sem inputs).
- Barra de ações sticky (Cancelar / Salvar) + validação inline.

### Mapeamento (editável × gerido-pela-casa)

| Campo | Fonte | Editável? |
|---|---|---|
| Nome público | `User.name` | ✅ |
| Data de nascimento | `perfil.data_nascimento` | ✅ |
| Endereço (não público) | `perfil.endereco` | ✅ |
| WhatsApp + visibilidade | `perfil.whatsapp`, `perfil.whatsapp_publico` | ✅ |
| Foto de perfil | `perfil` (Spatie Media, coleção `foto`) | ✅ upload |
| Setores (+ função coordenador) | `User.setores` (pivô `funcao`) | 🔒 read-only |
| Cargos | `User.cargos` | 🔒 read-only |
| Papel / nível | role Spatie (`nivel`) | 🔒 read-only |
| Sócio | `User.socio` | 🔒 read-only |

## Models / storage / traits

- **Migration incremental:** `Schema::table('perfis_membro', fn ($t) => $t->dropColumn('foto_perfil'))` (+ `down` que
  recria `string('foto_perfil')->nullable()`). Remover `'foto_perfil'` do `$fillable` do `PerfilMembro`.
- **`PerfilMembro implements HasMedia`**, `use InteractsWithMedia, RegistraImagensPadrao`; `COLECAO_FOTO='foto'`;
  `registerMediaCollections()` → `registrarColecaoImagem(self::COLECAO_FOTO, larguraWeb: 640)` (conversão `web` ≤640px;
  `thumb` 400×400 quadrado; o original é capado ≤2000px pelo listener padrão); accessors `fotoUrl()`/`fotoThumbUrl()`
  (mesmo padrão de `Palestrante`).
- **Trait `App\Models\Concerns\TemIniciais`:** algoritmo **idêntico ao atual** de `Palestrante::iniciais` (1ª letra das
  2 primeiras palavras, maiúsculas, fallback `'?'`); fonte do nome via método sobrescrevível `nomeParaIniciais()`
  (default `$this->nome`; `User` sobrescreve para `$this->name`). Aplicar em `User` e refatorar `Palestrante` para usá-la
  (remover o accessor local) — **os testes existentes de iniciais do Palestrante permanecem verdes** (mesma saída).
- **Perfil on-demand:** `auth()->user()->perfil()->firstOrCreate([])` no `ContaController` (cobre migrados sem linha de
  perfil).

## Segurança

- Área inteira sob middleware `auth`.
- O membro opera **só** sobre `auth()->user()` (nunca id vindo do request). O componente Livewire **não tem**
  propriedades graváveis para papel/socio/setor/cargo — blindagem contra edição da atuação.
- Upload validado (imagem, máx ~1 MB). CSRF nos forms; logout por POST. Filament (`/admin` + `canAccessPanel`) intacto.

## Plano de testes (feature + Livewire)

- Guest em `/minha-conta` → redirect para login; logado → 200.
- **Painel:** renderiza as próximas palestras (query com `>= today()`) e o estado vazio.
- **Perfil (view):** mostra editável × read-only; `frequentador` sem setor exibe a linha discreta; papel e Sócio sempre aparecem.
- **Header:** menu do membro quando logado; Entrar/Cadastrar quando guest.
- Pós-login redireciona para `/minha-conta`.
- **Livewire `EditarPerfil`:** validação (incl. `mimes:jpg,jpeg,png,webp` e `max:1024` na foto); salvar
  `name`/`data_nascimento`/`endereco`/`whatsapp`/toggle; upload de foto grava na Media Library (coleção `foto`) e gera as
  conversões `web`/`thumb`; **tentativa de setar papel/socio/setor é ignorada** (propriedade inexistente). O recorte é
  client-side (não testado no servidor); o teste do servidor cobre a validação e o armazenamento do arquivo enviado.
- **Iniciais:** a trait produz a saída atual; os **testes existentes de `Palestrante::iniciais` permanecem verdes**.

## Critério de pronto

Membro loga e cai em `/minha-conta`; vê o Painel (próximas palestras + atalhos) e o Meu Perfil (dados + atuação
read-only); edita nome/nascimento/endereço/WhatsApp/visibilidade e envia foto (Spatie Media, fallback iniciais); a
atuação/papel/sócio nunca são editáveis; o header do site reflete o estado de auth em **todas** as páginas;
`frequentador` sem setor tem um estado vazio elegante; responsivo/acessível; Filament intacto; suíte verde e Pint limpo.
