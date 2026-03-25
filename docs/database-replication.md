# Database Replication

Este documento explica como copiar a base de dados entre os perfis `production` e `sandbox` no projeto `ZentrumTask`.

## Objetivo

Este fluxo existe para permitir:

- arrancar o projeto mesmo quando a base de dados local ainda nao existe
- copiar os dados de `production` para `sandbox` sem depender do acesso ao Filament
- manter o mesmo comportamento entre o comando Artisan e a acao existente no painel Filament

## Perfis suportados

Os perfis sao definidos em [config/database.php](/Users/marcosborges/Desktop/GitHub/ZentrumTask/config/database.php) e lidos a partir do `.env`.

Perfis disponiveis:

- `sandbox`
- `production`

Variaveis esperadas no `.env`:

```dotenv
DB_MODE=sandbox

DB_DRIVER_SANDBOX=mysql
DB_HOST_SANDBOX=127.0.0.1
DB_PORT_SANDBOX=3306
DB_DATABASE_SANDBOX=...
DB_USERNAME_SANDBOX=...
DB_PASSWORD_SANDBOX=...

DB_DRIVER_PRODUCTION=mysql
DB_HOST_PRODUCTION=127.0.0.1
DB_PORT_PRODUCTION=3306
DB_DATABASE_PRODUCTION=...
DB_USERNAME_PRODUCTION=...
DB_PASSWORD_PRODUCTION=...
```

## Comando Artisan

Para copiar a base de dados de producao para sandbox:

```bash
php artisan db:replicate production sandbox
```

Para copiar no sentido inverso:

```bash
php artisan db:replicate sandbox production
```

O comando esta definido em [app/Console/Commands/ReplicateDatabaseCommand.php](/Users/marcosborges/Desktop/GitHub/ZentrumTask/app/Console/Commands/ReplicateDatabaseCommand.php).

## O que o comando faz

O comando reutiliza exatamente a mesma logica usada pela pagina do Filament em [app/Filament/Pages/DownloadDatabaseBackup.php](/Users/marcosborges/Desktop/GitHub/ZentrumTask/app/Filament/Pages/DownloadDatabaseBackup.php), atraves do servico [app/Support/DatabaseReplicationService.php](/Users/marcosborges/Desktop/GitHub/ZentrumTask/app/Support/DatabaseReplicationService.php).

O fluxo e este:

1. Ler os perfis `source` e `target` a partir da configuracao.
2. Validar que ambos usam `mysql` ou `mariadb`.
3. Verificar se a base de destino ja existe.
4. Fazer dump da base de origem.
5. Criar a base de destino se ela ainda nao existir.
6. Importar o dump para a base de destino.

## Tabelas ignoradas

Quando a base de destino ja existe, estas tabelas nao sao copiadas:

- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

Isto evita copiar dados temporarios ou filas pendentes entre ambientes.

Se a base de destino ainda nao existir, a primeira replicacao copia tudo, incluindo estas tabelas. Esse comportamento existe para permitir um arranque inicial completo da `sandbox`.

## Binarios usados

Por omissao:

- dump: `mysqldump` ou `mariadb-dump`
- import: `mysql` ou `mariadb`

Podes substituir os binarios com estas variaveis:

```dotenv
DB_BACKUP_BINARY=/caminho/para/mysqldump
DB_RESTORE_BINARY=/caminho/para/mysql
```

## Quando usar este comando

Usa este comando quando:

- a base `sandbox` ainda nao existe
- o projeto ainda nao arrancou ao ponto de conseguires abrir o Filament
- precisas de preparar rapidamente a base local com os dados de producao

Depois da primeira instalacao e da base estar funcional, podes continuar a usar a pagina do Filament para o mesmo processo.

## Cuidados

- `production -> sandbox` substitui totalmente os dados existentes na base `sandbox`
- `sandbox -> production` substitui totalmente os dados existentes na base `production`
- confirma sempre os valores de `DB_*_SANDBOX` e `DB_*_PRODUCTION` antes de executar
- usa com especial cuidado no sentido `sandbox -> production`

## Relacao com o Filament

No painel Filament existe uma pagina com as mesmas operacoes:

- `Copiar producao -> sandbox`
- `Copiar sandbox -> producao`

Essa pagina e apenas outra interface para a mesma logica de replicacao.
