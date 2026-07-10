# Spike — Filament Form v5 dentro de uma página do site

> Thiago Mourão — https://github.com/MouraoBSB — 2026-07-10
> Branch `spike/filament-forms-no-site` · **descartável, não mesclar**
> Stack conferida: `filament/filament ^5` (5.6.7) · `livewire/livewire ^4.3` · Tailwind v4 · PHP 8.3
>
> O **código** do spike (`app/Livewire/Spike/`, `spike-*.mjs`, `tests/Feature/Spike/`) está
> preservado na tag `spike-filament-forms-2026-07-10`, único âncora do commit `1ee530e`.
> Os screenshots citados abaixo (`spike-shots/`, 16 PNGs, 3,8 MB) **nunca foram versionados**:
> existem apenas no working tree de quem rodou o spike. As referências a eles ficam como
> registro do que foi observado, não como arquivo recuperável.

## VEREDITO: **PASSOU** — não caímos para o 2º painel Filament

Os 5 critérios foram atendidos. **Mas o spike só passou depois de achar a causa raiz**: `@filamentStyles`
**não** entrega o CSS dos componentes. Sem o tema compilado, o formulário renderiza como HTML cru.

| # | Critério | Resultado | Evidência |
|---|---|---|---|
| 1 | Renderiza no layout do site, header/footer intactos | ✅ ¹ | `spike-shots/comtema/minha-conta-spike-evento.png` |
| 2 | Salva de verdade, com validação do Filament | ✅ ² | `FormularioEventoSpikeTest` (3 testes) |
| 3 | Upload de imagem (Spatie) funciona fora do painel | ✅ | teste server-side + `POST /livewire/upload-file` → 200 no browser |
| 4 | ZERO regressão visual em `/eventos`, `/calendario`, `/minha-conta` | ✅ | hash + run de controle |
| 5 | Console limpo (sem Alpine duplicado, sem erro de Livewire 4) | ✅ | 0 erros/warnings, Alpine único 3.15.12 |

> ¹ Provado em **`x-layout.app`**, não em `x-layout.conta`. A rota do spike era
> `/minha-conta/spike-evento`, mas a página usava `<x-layout.app>` direto — o nome do
> screenshot sugere um `/minha-conta` que **não** foi exercitado. O `x-layout.conta` real
> engolia os slots `$headTop`/`$scripts` em silêncio; corrigido no PR #24.
>
> ² Inclui apenas a regra de campo `PeriodoEvento::horaFimAntesNoMesmoDia` (closure do schema).
> **Não** exercita `PeriodoEvento::erros()`, que é a rede server-side aplicada no painel pelo
> trait `ValidaPeriodoEvento` e cobre dois casos a mais: hora de término sem hora de início,
> e hora fora de `HH:MM`. O `FormularioEvento` do spike chamava `getState()` e salvava sem o trait.

**Fonte única provada:** o schema saiu para `App\Filament\Schemas\EventoForm::schema()` e é consumido
**pelos dois**: `EventoResource` (painel) e `App\Livewire\Spike\FormularioEvento` (site).
**Suíte inteira: 653 passed** — o painel continua funcionando com o schema extraído.

## A causa raiz (o que quase reprovou o spike)

`@filamentStyles` só emite os assets **registrados** (`cema-editor.css` + fonte Inter). Os estilos dos
componentes (`fi-*`) vivem no **tema compilado do painel** (`resources/css/filament/admin/theme.css` →
`theme-*.css`). Sem ele, o form renderiza cru: abas empilhadas, `<input type=file>` nativo, sem DatePicker.

- Antes (só `@filamentStyles`): `spike-shots/depois/minha-conta-spike-evento.png` — **form cru**.
- Depois (tema carregado): `spike-shots/comtema/minha-conta-spike-evento.png` — abas, RichEditor com
  toolbar, dropzones do FilePond, tudo estilizado.

**Correção aplicada:** carregar o tema compilado **escopado à página** e **antes** do `app.css` (para o
CSS do site vencer a cascata). Para isso, dois slots opcionais em `x-layout.app`: `$headTop` e `$scripts`.
Assim os assets do Filament **não vazam** para nenhuma outra página.

## Critério 4 — como foi medido (sem auto-engano)

`/eventos` ficou **byte-idêntico** (SHA-256 igual antes/depois) e o HTML não tem **nenhuma** ocorrência de
"filament". `/calendario` e `/minha-conta` mudaram poucos bytes — mas mudam **também entre dois runs do
mesmo código** (run de controle), porque o calendário tem *countdown ao vivo*. Ou seja: ruído dinâmico,
não regressão.

## Problemas encontrados

1. **[Resolvido] CSS de componentes ausente.** Ver causa raiz acima.
2. **[Resolvido] Assets globais vazariam.** `@filamentStyles`/`@filamentScripts` no layout global injetam
   CSS/JS em todas as páginas. Escopados via slots.
3. **[Não é defeito] DatePicker `native(false)`** tem input `readonly` + painel flutuante (`x-float`) —
   dificulta automação (Playwright), usuário real clica normalmente. Save/validação foram provados
   server-side.
4. **[Não é defeito] `InvalidStateError: source image could not be decoded`** no console: causado por um
   PNG sintético 4×4 da minha fixture. Com imagem real do repo → **console limpo**.
5. **[Confirmado OK] Alpine não duplica.** Instância única (3.15.12) vinda do Livewire; `@filamentScripts`
   apenas registra plugins em `alpine:init`. Zero falha HTTP.

## Custos e decisões ANTES de implementar

1. **Peso do CSS.** `theme-*.css` = **609 KB** (~63 KB gzip). Aceitável em páginas autenticadas de
   `/minha-conta`; **fere o orçamento de performance** se um form embutido for para página pública.
   Opção: gerar um **tema enxuto do site** (só os componentes de formulário usados) em vez de reusar o do painel.
2. **Preflight duplo.** O tema traz o preflight do Tailwind; o site também. Não quebrou header/footer
   (evidência visual), mas convivem na mesma página — vigiar quando o form entrar em telas com mais CSS do site.
3. **Fonte.** O tema importa Poppins/Work Sans e o Filament injeta Inter. Sem troca visível no header/footer,
   mas é ponto de atenção.

## Artefatos (branch `spike/filament-forms-no-site`)

- `app/Filament/Schemas/EventoForm.php` — schema como fonte única (**o que vale a pena manter**).
- `app/Filament/Resources/Eventos/EventoResource.php` — passa a consumir o schema.
- `app/Livewire/Spike/FormularioEvento.php` + views + rota `/minha-conta/spike-evento` — descartáveis.
- `resources/views/components/layout/app.blade.php` — slots `$headTop`/`$scripts` (**padrão a manter**).
- `tests/Feature/Spike/FormularioEventoSpikeTest.php` — provas dos critérios 2 e 3.
- Scripts de verificação: `spike-capture.mjs`, `spike-diag.mjs`, `spike-e2e.mjs`, `spike-upload.mjs`.
- Screenshots em `spike-shots/{antes,depois,comtema,controle}/`.
