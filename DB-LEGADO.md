# DB-LEGADO — Acesso somente leitura ao banco do WordPress atual

Objetivo: permitir que o desenvolvimento consulte **ao vivo** a estrutura e os
dados reais do WordPress (cemanet.org.br) para uma migração precisa — sem nenhum
risco à produção.

## Modelo de segurança (inegociável)

- Acesso **somente leitura**: usuário MySQL com apenas `SELECT` (nunca o root).
- **Nada exposto à internet**: o banco continua fechado; o acesso é por **túnel SSH**.
- A conexão `legado` do Laravel **nunca** roda migrations/seeders/escritas.
- Credenciais só no `.env` (gitignored). O arquivo de stack do WP (com a senha de
  root) **não** entra em nenhum repositório.

## Contexto do servidor atual

O WordPress roda em **Docker Swarm** no VPS: serviço `mysql_cemanet`
(**Percona 8**, banco `wordpress_cemanet`, charset `utf8mb4_general_ci`), atrás do
Traefik. A porta 3306 **não é publicada** — fica só na rede overlay `network_public`.

## Passo 1 — Criar o usuário somente leitura (uma vez, no VPS)

```bash
# no VPS, abrir o MySQL como root (senha no cofre/arquivo de stack — NÃO versionar)
docker exec -it $(docker ps -qf name=mysql_cemanet) mysql -uroot -p
```
```sql
CREATE USER 'cema_leitor'@'%' IDENTIFIED BY 'TROQUE_POR_UMA_SENHA_FORTE';
GRANT SELECT, SHOW VIEW ON wordpress_cemanet.* TO 'cema_leitor'@'%';
FLUSH PRIVILEGES;
```

## Passo 2 — Expor o MySQL ao localhost do VPS (sem expor à internet)

Como o Swarm não publica a porta, suba uma ponte que escuta **só no loopback** do
VPS e encaminha para o serviço na rede overlay (não toca no stack do WordPress):

```bash
# no VPS
docker run -d --restart unless-stopped --name cema-db-bridge \
  --network network_public -p 127.0.0.1:3306:3306 \
  alpine/socat tcp-listen:3306,fork,reuseaddr tcp-connect:mysql_cemanet:3306
```
Isso disponibiliza o banco em `127.0.0.1:3306` **apenas dentro do VPS** (loopback).
Alternativa: publicar a porta no stack e bloquear 3306 no firewall (ufw) deixando
abertos só 22/80/443 — porém a ponte `socat` acima é mais isolada e não altera a produção.

## Passo 3 — Abrir o túnel SSH (na sua máquina)

```bash
# encaminha a porta local 3307 -> 127.0.0.1:3306 do VPS
ssh -N -L 3307:127.0.0.1:3306 deploy@SEU_VPS
# persistente (reconecta sozinho):
autossh -M 0 -N -L 3307:127.0.0.1:3306 deploy@SEU_VPS
```

## Passo 4 — Conexão `legado` no Laravel

Em `.env` (preencher com o usuário só-leitura do Passo 1):
```
LEGADO_DB_HOST=127.0.0.1
LEGADO_DB_PORT=3307
LEGADO_DB_DATABASE=wordpress_cemanet
LEGADO_DB_USERNAME=cema_leitor
LEGADO_DB_PASSWORD=...
```

Em `config/database.php`, adicionar dentro de `'connections' => [ ... ]`:
```php
'legado' => [
    'driver'     => 'mysql',
    'host'       => env('LEGADO_DB_HOST', '127.0.0.1'),
    'port'       => env('LEGADO_DB_PORT', '3307'),
    'database'   => env('LEGADO_DB_DATABASE', 'wordpress_cemanet'),
    'username'   => env('LEGADO_DB_USERNAME'),
    'password'   => env('LEGADO_DB_PASSWORD'),
    'charset'    => 'utf8mb4',
    'collation'  => 'utf8mb4_general_ci',
    'prefix'     => 'wp_',
    'strict'     => true,
    // SOMENTE LEITURA — nunca migrations/seeders/escritas nesta conexão.
],
```
Uso: `DB::connection('legado')->select('...')` ou models com `protected $connection = 'legado';`.
Confirme o prefixo das tabelas com `SHOW TABLES;` (padrão WordPress é `wp_`).

## Passo 5 — Introspecção (rodar uma vez e documentar abaixo)

```sql
SHOW TABLES;                              -- confirmar prefixo (wp_) e listar tudo
SHOW TABLES LIKE '%jet_rel%';            -- relações Jet (107 palestrante, 108 diretor)
DESCRIBE wp_jet_rel_107;                  -- colunas: parent_object_id / child_object_id
DESCRIBE wp_jet_rel_108;

-- chaves de meta por CPT (ex.: palestra_publica)
SELECT pm.meta_key, COUNT(*) AS n
FROM wp_postmeta pm JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.post_type = 'palestra_publica'
GROUP BY pm.meta_key ORDER BY n DESC;

-- taxonomia assuntos-principais (hierarquia)
SELECT t.term_id, t.name, t.slug, tt.parent
FROM wp_terms t JOIN wp_term_taxonomy tt ON tt.term_id = t.term_id
WHERE tt.taxonomy = 'assuntos-principais' ORDER BY tt.parent, t.name;
```

> **Documentar aqui o resultado** (tabelas relevantes, colunas das relações Jet,
> chaves de meta por CPT) para virar referência permanente do mapeamento WP→novo,
> complementando `DATA-MODEL.md`.

## Regras duras

- Apenas `SELECT`/`SHOW`/`DESCRIBE`. Nada de `INSERT/UPDATE/DELETE/DDL`.
- Nunca usar o usuário root para esta conexão.
- Não publicar o MySQL na internet; acesso só por túnel SSH.
- Não versionar credenciais nem o arquivo de stack do WordPress.
