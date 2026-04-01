<?php

namespace App\Filament\Pages;

use App\Support\DatabaseReplicationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnitEnum;

class DownloadDatabaseBackup extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static UnitEnum|string|null $navigationGroup = 'Administracao';

    protected static ?string $navigationLabel = 'Backup da Base de Dados';

    protected static ?int $navigationSort = 1000;

    protected string $view = 'filament.pages.download-database-backup';

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('optimizeClear')
                ->label('Correr optimize:clear')
                ->color('primary')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->requiresConfirmation()
                ->modalHeading('Limpar caches do Laravel')
                ->modalDescription('Corre o comando optimize:clear no servidor atual para limpar config, routes, views e caches.')
                ->action(fn () => $this->runOptimizeClear()),
            Action::make('productionToSandbox')
                ->label('Copiar producao -> sandbox')
                ->color('danger')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->requiresConfirmation()
                ->modalHeading('Copiar base de dados externa para interna')
                ->modalDescription('Substitui totalmente a base de dados sandbox pelos dados da base externa.')
                ->action(fn () => $this->replicateDatabase('production', 'sandbox')),
            Action::make('sandboxToProduction')
                ->label('Copiar sandbox -> producao')
                ->color('warning')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->requiresConfirmation()
                ->modalHeading('Copiar base de dados interna para externa')
                ->modalDescription('Substitui totalmente a base de dados externa pelos dados da base sandbox.')
                ->action(fn () => $this->replicateDatabase('sandbox', 'production')),
            Action::make('toggleMode')
                ->label(fn (): string => $this->toggleLabel())
                ->color('success')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->requiresConfirmation()
                ->modalHeading('Alternar entre sandbox e producao')
                ->modalDescription('Atualiza a variavel DB_MODE no ficheiro .env e recarrega a configuracao.')
                ->action(fn () => $this->toggleDatabaseMode()),
        ];
    }

    protected function replicateDatabase(string $sourceMode, string $targetMode): void
    {
        $result = app(DatabaseReplicationService::class)->replicate($sourceMode, $targetMode);

        $notification = Notification::make()
            ->title($result->title)
            ->body($result->message);

        if ($result->successful) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }

    protected function runOptimizeClear(): void
    {
        try {
            Artisan::call('optimize:clear');

            $output = trim(Artisan::output());
            $body = $output !== ''
                ? mb_strimwidth($output, 0, 400, '...')
                : 'Caches limpas com sucesso.';

            Notification::make()
                ->success()
                ->title('optimize:clear executado')
                ->body($body)
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Falha ao correr optimize:clear')
                ->body($exception->getMessage())
                ->send();
        }
    }

    protected function toggleLabel(): string
    {
        $currentMode = (string) Config::get('database.mode', 'sandbox');
        $targetMode = $currentMode === 'production' ? 'sandbox' : 'production';

        return "Alternar para {$targetMode}";
    }

    protected function toggleDatabaseMode(): void
    {
        $currentMode = (string) Config::get('database.mode', 'sandbox');
        $targetMode = $currentMode === 'production' ? 'sandbox' : 'production';

        if (! $this->persistEnvValue('DB_MODE', $targetMode)) {
            Notification::make()
                ->danger()
                ->title('Nao foi possivel alterar DB_MODE')
                ->body('Falha ao escrever no ficheiro .env.')
                ->send();

            return;
        }

        Config::set('database.mode', $targetMode);

        $profile = $this->databaseProfile($targetMode);

        if ($profile !== null) {
            $this->applyProfileToConnections($profile);
            $this->refreshDatabaseConnections();
        }

        Notification::make()
            ->success()
            ->title('Modo alterado')
            ->body("DB_MODE atualizado para {$targetMode}.")
            ->send();
    }

    protected function persistEnvValue(string $key, string $value): bool
    {
        $envPath = base_path('.env');

        $contents = file_exists($envPath) ? file_get_contents($envPath) : '';

        if ($contents === false) {
            return false;
        }

        $line = "{$key}={$value}";
        $pattern = '/^'.$key.'=.*/m';

        if (preg_match($pattern, $contents) === 1) {
            $contents = (string) preg_replace($pattern, $line, $contents);
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        return file_put_contents($envPath, $contents) !== false;
    }

    /**
     * @return array{
     *     driver: string,
     *     host: string,
     *     port: string|int,
     *     database: string,
     *     username: string,
     *     password: string|null
     * }|null
     */
    protected function databaseProfile(string $mode): ?array
    {
        $profiles = Config::get('database.profiles', []);

        return $profiles[$mode] ?? null;
    }

    /**
     * @param  array{
     *     driver: string,
     *     host: string,
     *     port: string|int,
     *     database: string,
     *     username: string,
     *     password: string|null
     * }  $profile
     */
    protected function applyProfileToConnections(array $profile): void
    {
        foreach (['mysql', 'mariadb'] as $connection) {
            Config::set("database.connections.{$connection}.host", $profile['host']);
            Config::set("database.connections.{$connection}.port", $profile['port']);
            Config::set("database.connections.{$connection}.database", $profile['database']);
            Config::set("database.connections.{$connection}.username", $profile['username']);
            Config::set("database.connections.{$connection}.password", $profile['password']);
        }
    }

    protected function refreshDatabaseConnections(): void
    {
        foreach (['mysql', 'mariadb'] as $connection) {
            DB::purge($connection);
            DB::reconnect($connection);
        }
    }

}
