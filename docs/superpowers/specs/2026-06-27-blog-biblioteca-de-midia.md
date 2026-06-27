# Blog — Biblioteca de mídia reutilizável (escolher imagem já enviada)

Pedido do dono: ao clicar no **clipe** para subir uma foto no editor, ter também a opção de
**escolher uma foto já enviada** (em vez de re-subir). Motivos: **produtividade** (achar e reusar)
e **disco** (evitar cópias duplicadas). É a "Biblioteca de mídia" estilo WordPress.

## Necessidade
- No attach do editor (e idealmente na destacada/galeria): **"subir nova" OU "escolher existente"**.
- Uma **pasta/galeria navegável** das imagens já enviadas (com preview/busca).
- Não duplicar a mesma foto.

## Realidade atual (por que não é trivial)
O storage roda em **Spatie Media Library**, modelo **por-registro**: cada post **possui** suas
próprias mídias (cópias). **Não há** biblioteca global navegável nem reuso entre posts de fábrica.
A tabela `media` é global (um índice), **mas** a posse e o **cleanup são por registro** — então
reusar, em outro post, um arquivo "dono" de um post quebra ao deletar o dono (arquivo some →
referência quebrada). Ou seja: isto é uma feature de **gestão de mídia**, não um ajuste pequeno.

## Opções
- **A) Gerenciador de mídia dedicado** (ex.: **Curator** — `awcodes/filament-curator`): biblioteca
  **central**, navegar, **reusar entre posts**, dedup, *picker* em formulários/editor. **Melhor
  aderência ao pedido.** Custo/decisão: é um sistema **paralelo/substituto** à Spatie ML que
  acabamos de montar → decidir se **substitui** a ML (retrabalho) ou **coexiste** (dois sistemas);
  e avaliar integração com o **attach do RichEditor**.
- **B) Browser custom sobre a `media` da ML:** listar as mídias existentes para inserir; porém
  reusar arquivo de outro post conflita com o **cleanup por-registro** da ML. Viável só com um
  **dono compartilhado** ("Biblioteca") e cuidado no cleanup. Reinventa parte do Curator.
- **C) Mínimo — dedup por hash:** impedir que subir o **mesmo arquivo** crie cópia nova (checksum).
  Ajuda o disco, mas **não** entrega a UX de navegar/escolher.

## Perguntas ao agente (feasibility — responder antes de decidir)
1. Dado que padronizamos em Spatie ML, qual o caminho mais limpo p/ uma biblioteca reutilizável:
   **Curator** (substituir a ML? coexistir só p/ o corpo?) ou **browser custom sobre a ML**?
2. O Curator integra com o **attach do RichEditor** (ícone de clipe) de forma turnkey, ou exige custom?
3. Impacto no que já foi entregue (provider do corpo, coleções destacada/galeria/og, cap na entrada,
   cleanup por `data-id`=uuid)?
4. Posse/cleanup ao **reusar** uma imagem em vários posts (não apagar arquivo em uso) — como tratar?
5. Recomendação do agente + **esforço estimado**.

## Feasibility (resposta do agente, 2026-06-27) — recomendação: **B**

Construir a biblioteca **sobre a Spatie ML** (Opção B), tratando a mídia como **referência por
URL** (`<img src>`), com **dono central** (singleton/Resource) que possui o pool numa coleção ML.
Pontos-chave:
- **Generaliza o fix do corpo:** imagens do corpo viram **sempre referência** (fora do cleanup) →
  **elimina a classe de bug** "editor apaga imagem migrada". É robustez, não só feature.
- **Curator não compensa:** seu forte é o `CuratorPicker` (campo de form); o **attach inline do
  editor é custom de qualquer jeito** → a vantagem turnkey não se aplica, e traria um **2º sistema
  de mídia** paralelo à ML. Caminho limpo = B.
- **Não mexer no clipe:** tool próprio na toolbar **"Inserir da biblioteca"** → modal de
  busca/preview → insere `<img src>`.
- destacada/galeria/og **não afetados** (por-post). Cap-listener e conversões **reaproveitados**.
- **Reuso/cleanup:** dono central nunca deletado por operação de post; **dedup por SHA-256**;
  rastreio de uso (pivot `post ↔ biblioteca_midia`) + bloqueio/aviso ao deletar imagem em uso.
- **Esforço:** B ≈ **3–5 dias** (fatia própria: model dono + migração corpo→biblioteca + Resource
  navegar/buscar/CRUD + tool "Inserir da biblioteca" + dedup + rastreio + testes). C (dedup só) ≈
  0,5d (não entrega navegar/escolher). A (Curator) ≈ 2–3d mas com 2 sistemas — **não recomendado**.

### Cuidados de design a incorporar no spec (revisão do Thiago/Claude)
1. **Portabilidade da URL:** referência por `/storage/...` quebra se a mídia migrar p/ **S3/CDN**.
   Projetar a referência p/ **sobreviver a mudança de storage** — servir por **rota do app**
   (`/midia/{id}/web`) ou guardar o **id** e montar a URL no render. (Risco **já existe** hoje no
   corpo → resolver junto.)
2. **Deleção autoritativa:** na hora de apagar, **varredura sob demanda** do uso (bloquear/avisar),
   não confiar só no pivot (pode desatualizar).

## Decisão — CONFIRMADA pelo dono (2026-06-27)
- **Opção B** (biblioteca sobre a ML, por referência). **Dedup-C isolado descartado** (B já inclui).
- **Sequenciamento:** fazer **depois** do reimport/verificação e dos 5 ajustes de UX do editor —
  é uma **fatia própria** (spec → plano → SDD), não atravessar o que está em voo.
- **Portabilidade da URL aprovada como princípio do projeto** (rota estável p/ sobreviver à
  migração de storage; vale também p/ as imagens do corpo já existentes). Registrado em `PROJECT.md`.
- Ao abrir a fatia: incorporar os 2 cuidados (URL portável + deleção autoritativa).
