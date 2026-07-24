{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23 --}}
{{-- Molde dos forms Filament embutidos (Fase E): o tema escopado do site + a paleta raw do Filament.
     A paleta vai INLINE (style), NÃO no theme.css: o transform do Tailwind v4 poda as variáveis raw da
     paleta (gray/primary/...) declaradas à mão em CSS que ele processa (medido: somem do bundle em
     :root, html:root, "@theme static" e "@layer base", minificado e não). Inline no head é imune ao
     build — é como o @filamentStyles do /admin injeta a paleta. Sem ela, o trilho do fi-toggle
     (bg-gray-200 → var(--gray-200)) computa transparent e o médium não consegue enviar.
     Literais oklch CEMA capturados do :root que o /admin SERVIDO emite (a paleta default do Filament
     seria âmbar). Escopado: só as 3 páginas Fase E usam este componente.
     Ver docs/superpowers/specs/2026-07-23-molde-filament-no-site-paleta.md. --}}
@vite('resources/css/filament/site/theme.css')
<style>
    :root {
        /* Primária — roxo institucional (#4E4483 no 600). */
        --primary-50: oklch(0.97 0.013 286.149);
        --primary-100: oklch(0.935 0.028 288.028);
        --primary-200: oklch(0.875 0.051 289.02);
        --primary-300: oklch(0.791 0.072 288.228);
        --primary-400: oklch(0.66 0.091 288.808);
        --primary-500: oklch(0.54 0.1 288.936);
        --primary-600: oklch(0.428 0.102 288.629);
        --primary-700: oklch(0.386 0.091 288.9);
        --primary-800: oklch(0.347 0.082 289.062);
        --primary-900: oklch(0.309 0.072 288.166);
        --primary-950: oklch(0.224 0.057 288.615);

        /* Cinza — Color::Neutral (neutro puro). Pinta o trilho OFF do toggle. */
        --gray-50: oklch(0.985 0 0);
        --gray-100: oklch(0.97 0 0);
        --gray-200: oklch(0.922 0 0);
        --gray-300: oklch(0.87 0 0);
        --gray-400: oklch(0.708 0 0);
        --gray-500: oklch(0.556 0 0);
        --gray-600: oklch(0.439 0 0);
        --gray-700: oklch(0.371 0 0);
        --gray-800: oklch(0.269 0 0);
        --gray-900: oklch(0.205 0 0);
        --gray-950: oklch(0.145 0 0);

        /* Danger — #C33A36. */
        --danger-50: oklch(0.97717647058824 0.01395454545455 26.365);
        --danger-100: oklch(0.95035294117647 0.03272727272727 26.365);
        --danger-200: oklch(0.90547058823529 0.06318181818182 26.365);
        --danger-300: oklch(0.84047058823529 0.10604545454546 26.365);
        --danger-400: oklch(0.75352941176471 0.15027272727273 26.365);
        --danger-500: oklch(0.68270588235294 0.17009090909091 26.365);
        --danger-600: oklch(0.59782352941176 0.16913636363636 26.365);
        --danger-700: oklch(0.51494117647059 0.14940909090909 26.365);
        --danger-800: oklch(0.44611764705882 0.12331818181818 26.365);
        --danger-900: oklch(0.39458823529412 0.09963636363636 26.365);
        --danger-950: oklch(0.27788235294118 0.07136363636364 26.365);

        /* Info — #6E9FCB. */
        --info-50: oklch(0.97717647058824 0.01395454545455 246.479);
        --info-100: oklch(0.95035294117647 0.03272727272727 246.479);
        --info-200: oklch(0.90547058823529 0.06318181818182 246.479);
        --info-300: oklch(0.84047058823529 0.10604545454546 246.479);
        --info-400: oklch(0.75352941176471 0.15027272727273 246.479);
        --info-500: oklch(0.68270588235294 0.17009090909091 246.479);
        --info-600: oklch(0.59782352941176 0.16913636363636 246.479);
        --info-700: oklch(0.51494117647059 0.14940909090909 246.479);
        --info-800: oklch(0.44611764705882 0.12331818181818 246.479);
        --info-900: oklch(0.39458823529412 0.09963636363636 246.479);
        --info-950: oklch(0.27788235294118 0.07136363636364 246.479);

        /* Warning — #F2A81E (dourado da marca). */
        --warning-50: oklch(0.97717647058824 0.01395454545455 75.703);
        --warning-100: oklch(0.95035294117647 0.03272727272727 75.703);
        --warning-200: oklch(0.90547058823529 0.06318181818182 75.703);
        --warning-300: oklch(0.84047058823529 0.10604545454546 75.703);
        --warning-400: oklch(0.75352941176471 0.15027272727273 75.703);
        --warning-500: oklch(0.68270588235294 0.17009090909091 75.703);
        --warning-600: oklch(0.59782352941176 0.16913636363636 75.703);
        --warning-700: oklch(0.51494117647059 0.14940909090909 75.703);
        --warning-800: oklch(0.44611764705882 0.12331818181818 75.703);
        --warning-900: oklch(0.39458823529412 0.09963636363636 75.703);
        --warning-950: oklch(0.27788235294118 0.07136363636364 75.703);

        /* Success — #008000. */
        --success-50: oklch(0.97717647058824 0.01395454545455 142.495);
        --success-100: oklch(0.95035294117647 0.03272727272727 142.495);
        --success-200: oklch(0.90547058823529 0.06318181818182 142.495);
        --success-300: oklch(0.84047058823529 0.10604545454546 142.495);
        --success-400: oklch(0.75352941176471 0.15027272727273 142.495);
        --success-500: oklch(0.68270588235294 0.17009090909091 142.495);
        --success-600: oklch(0.59782352941176 0.16913636363636 142.495);
        --success-700: oklch(0.51494117647059 0.14940909090909 142.495);
        --success-800: oklch(0.44611764705882 0.12331818181818 142.495);
        --success-900: oklch(0.39458823529412 0.09963636363636 142.495);
        --success-950: oklch(0.27788235294118 0.07136363636364 142.495);
    }
</style>
