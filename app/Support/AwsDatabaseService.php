<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class AwsDatabaseService
{
    /** @return array<string, mixed> */
    protected function configuration(): array
    {
        return config('database.replication.production_dump');
    }

    protected function identifier(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/D', $value) !== 1) {
            throw new RuntimeException('Identificador AWS ou MySQL invalido.');
        }

        return $value;
    }

    public function container(): string
    {
        return $this->identifier((string) ($this->configuration()['container'] ?? ''));
    }

    /** @return array{host: string, user: string, key: string} */
    public function access(): array
    {
        $configuration = $this->configuration();
        $region = (string) ($configuration['aws_region'] ?? '');
        $instance = $this->identifier((string) ($configuration['lightsail_instance'] ?? ''));
        $user = $this->identifier((string) ($configuration['ssh_user'] ?? 'ubuntu'));

        if ($region === '') {
            throw new RuntimeException('Configure a regiao AWS.');
        }

        $host = trim($this->aws(['lightsail', 'get-instance', '--instance-name', $instance,
            '--region', $region, '--query', 'instance.publicIpAddress', '--output', 'text']));

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException('A AWS nao devolveu um endereco valido para a instancia.');
        }

        $key = trim($this->aws(['lightsail', 'download-default-key-pair', '--region', $region,
            '--query', 'privateKeyBase64', '--output', 'text']));

        if (! str_contains($key, 'PRIVATE KEY-----')) {
            $key = base64_decode($key, true) ?: '';
        }

        if (! str_contains($key, 'PRIVATE KEY-----')) {
            throw new RuntimeException('A AWS nao devolveu uma chave SSH valida.');
        }

        $path = tempnam(sys_get_temp_dir(), 'zentrum-aws-');

        if ($path === false) {
            throw new RuntimeException('Nao foi possivel criar a chave temporaria.');
        }

        try {
            if (file_put_contents($path, $key.PHP_EOL) === false) {
                throw new RuntimeException('Nao foi possivel escrever a chave temporaria.');
            }

            if (DIRECTORY_SEPARATOR === '\\') {
                $username = (string) (getenv('USERNAME') ?: '');

                if ($username === '') {
                    throw new RuntimeException('Utilizador Windows indisponivel.');
                }

                (new Process(['icacls', $path, '/inheritance:r', '/grant:r', $username.':(R)']))->mustRun();
            } elseif (! chmod($path, 0600)) {
                throw new RuntimeException('Nao foi possivel proteger a chave temporaria.');
            }
        } catch (Throwable $exception) {
            $this->deleteKey($path);
            throw $exception;
        }

        return ['host' => $host, 'user' => $user, 'key' => $path];
    }

    /** @param list<string> $arguments */
    protected function aws(array $arguments): string
    {
        $process = new Process([(string) ($this->configuration()['aws_binary'] ?? 'aws'), ...$arguments, '--no-cli-pager'], base_path());
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            if (str_contains(strtolower($process->getErrorOutput()), 'session has expired')) {
                throw new RuntimeException('A sessao AWS expirou. Execute aws login neste computador e volte a verificar a ligacao.');
            }

            throw new RuntimeException('Falha no acesso AWS. Verifique credenciais, regiao e permissoes Lightsail.');
        }

        return $process->getOutput();
    }

    /** @param array{host: string, user: string, key: string} $access
     * @param  list<string>  $arguments
     * @return list<string>
     */
    protected function sshArguments(array $access, array $arguments = []): array
    {
        return [(string) ($this->configuration()['ssh_binary'] ?? 'ssh'), '-i', $access['key'],
            '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=20', '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=3', ...$arguments,
            $access['user'].'@'.$access['host']];
    }

    /** @param array{host: string, user: string, key: string} $access */
    public function remote(array $access, string $script): Process
    {
        $process = new Process([...$this->sshArguments($access), 'bash -lc '.$this->quote($script)], base_path());
        $process->setTimeout(1500);

        return $process;
    }

    protected function quote(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }

    public function dockerScript(string $script): string
    {
        return 'sudo docker exec -i '.$this->container().' bash -lc '.$this->quote($script);
    }

    public function credentialsScript(): string
    {
        return 'export MYSQL_PWD="${DB_PASSWORD_PRODUCTION:-${DB_PASSWORD:-}}"; '
            .'db_host="${DB_HOST_PRODUCTION:-${DB_HOST:-127.0.0.1}}"; '
            .'db_port="${DB_PORT_PRODUCTION:-${DB_PORT:-3306}}"; '
            .'db_user="${DB_USERNAME_PRODUCTION:-${DB_USERNAME:-root}}"; ';
    }

    /** @param array{host: string, user: string, key: string} $access */
    public function execute(array $access, string $script): string
    {
        $process = $this->remote($access, $script);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('A operacao SSH na AWS falhou. Verifique a instancia, o contentor e as permissoes MySQL.');
        }

        return trim($process->getOutput());
    }

    public function diagnose(): string
    {
        if (! config('database-management.local')) {
            app(DatabaseReplicationService::class)->probe('production');

            return 'Ligacao MySQL de producao validada neste servidor AWS.';
        }

        $access = $this->access();

        try {
            $this->execute($access, $this->dockerScript($this->credentialsScript()
                .'mysql --protocol=TCP --host="$db_host" --port="$db_port" --user="$db_user" --batch --skip-column-names --execute="SELECT 1" '
                .$this->quote($this->identifier((string) config('database.profiles.production.database')))));

            return 'AWS Lightsail, SSH, contentor e MySQL validados.';
        } finally {
            $this->deleteKey($access['key']);
        }
    }

    public function clearCaches(): void
    {
        $access = $this->access();

        try {
            $containers = [$this->container(), $this->identifier((string) config('database-management.worker_container')),
                $this->identifier((string) config('database-management.scheduler_container'))];
            $script = 'set -e; ';

            foreach ($containers as $container) {
                $script .= 'sudo docker exec '.$container.' php artisan optimize:clear --no-interaction >/dev/null; ';
            }

            $script .= 'sudo docker restart '.$containers[1].' '.$containers[2].' >/dev/null';
            $this->execute($access, $script);
        } finally {
            $this->deleteKey($access['key']);
        }
    }

    /** @param array{host: string, user: string, key: string} $access */
    public function tunnelProcess(array $access): Process
    {
        $port = (int) config('database-management.tunnel_port');
        $forward = (string) ($this->configuration()['tunnel_target'] ?? '127.0.0.1:3306');

        if ($port < 1024 || $port > 65535 || preg_match('/^[A-Za-z0-9_.-]+:[0-9]+$/D', $forward) !== 1) {
            throw new RuntimeException('Configuracao do tunel SSH invalida.');
        }

        $process = new Process($this->sshArguments($access, ['-N', '-o', 'ExitOnForwardFailure=yes',
            '-L', '127.0.0.1:'.$port.':'.$forward]), base_path());
        $process->setTimeout(null);
        $process->disableOutput();

        return $process;
    }

    public function ensureTunnel(): void
    {
        if (! config('database-management.local')) {
            return;
        }

        app(DatabaseManagementService::class)->withLock('tunnel-start', fn () => $this->ensureConnectedTunnel(), 60);
    }

    protected function ensureConnectedTunnel(): void
    {

        $directory = app(DatabaseManagementService::class)->directory();
        $statePath = $directory.'/tunnel.json';
        $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : null;

        if ($this->portOpen()) {
            if (! is_array($state) || ($state['port'] ?? null) !== (int) config('database-management.tunnel_port')
                || ($state['instance'] ?? null) !== ($this->configuration()['lightsail_instance'] ?? null)
                || ($state['heartbeat'] ?? 0) < time() - 30) {
                throw new RuntimeException('A porta do tunel esta ocupada por uma ligacao que nao pertence a esta aplicacao.');
            }

            $this->probeTunnel();

            return;
        }

        if (is_file($statePath)) {
            unlink($statePath);
        }

        app(ManagedProcessLauncher::class)->launch(['db:aws-tunnel', '--no-interaction'], $directory.'/tunnel.log');

        for ($attempt = 0; $attempt < 100; $attempt++) {
            usleep(500000);

            if ($this->portOpen()) {
                $this->probeTunnel();

                return;
            }

            $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : null;

            if (($state['error'] ?? null) !== null && ($state['heartbeat'] ?? 0) >= time() - 60) {
                throw new RuntimeException((string) $state['error']);
            }
        }

        throw new RuntimeException('O tunel AWS nao ficou disponivel. Consulte o diagnostico e os logs privados.');
    }

    protected function probeTunnel(): void
    {
        $profile = config('database.profiles.production');
        try {
            $connection = new \PDO('mysql:host=127.0.0.1;port='.(int) config('database-management.tunnel_port')
                .';dbname='.$profile['database'], $profile['username'], $profile['password'], [\PDO::ATTR_TIMEOUT => 5]);
            $identity = $connection->query("SELECT CONCAT(@@hostname, ':', @@server_id)")->fetchColumn();
        } catch (\PDOException) {
            throw new RuntimeException('Nao foi possivel validar a base de producao atraves do tunel AWS.');
        }
        $state = json_decode((string) file_get_contents(app(DatabaseManagementService::class)->directory().'/tunnel.json'), true);

        if (! is_string($identity) || $identity !== ($state['identity'] ?? null)) {
            throw new RuntimeException('O tunel nao aponta para o servidor MySQL validado na AWS.');
        }
    }

    /** @param array<string, mixed> $state */
    public function recordTunnelState(array $state): void
    {
        $path = app(DatabaseManagementService::class)->directory().'/tunnel.json';
        $temporary = $path.'.'.getmypid().'.tmp';

        if (file_put_contents($temporary, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false || ! rename($temporary, $path)) {
            throw new RuntimeException('Nao foi possivel guardar o estado do tunel SSH.');
        }
    }

    public function portOpen(): bool
    {
        $socket = @fsockopen('127.0.0.1', (int) config('database-management.tunnel_port'), $errorCode, $errorMessage, 0.2);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }

    public function deleteKey(string $path): void
    {
        if (! is_file($path)) {
            return;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            (new Process(['icacls', $path, '/grant:r', (string) getenv('USERNAME').':(F)']))->run();
        }

        if (! unlink($path)) {
            throw new RuntimeException('Nao foi possivel remover a chave SSH temporaria.');
        }
    }
}
