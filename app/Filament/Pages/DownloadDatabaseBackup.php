<?php

namespace App\Filament\Pages;

use App\Support\DatabaseManagementService;
use App\Support\DatabaseModeService;
use App\Support\DatabaseReplicationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

class DownloadDatabaseBackup extends Page
{
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Administracao';

    protected static ?string $navigationLabel = 'Gestao de Bases de Dados';

    protected static ?string $title = 'Gestao de Bases de Dados';

    protected static ?int $navigationSort = 1000;

    protected string $view = 'filament.pages.download-database-backup';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel();

        return $user !== null && $panel !== null && $user->canAccessPanel($panel);
    }

    protected function authorizeManagement(bool $localOnly = false): void
    {
        abort_unless(static::canAccess(), 403);
        abort_if($localOnly && ! config('database-management.local'), 403);
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('diagnose')
                ->label('Verificar ligacao AWS')
                ->icon(Heroicon::OutlinedSignal)
                ->action(fn () => $this->startOperation('diagnose', 'production')),
            Action::make('backup')
                ->label('Criar backup')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->schema([
                    Select::make('mode')->label('Base de dados')
                        ->options(fn (): array => config('database-management.local')
                            ? ['production' => 'Producao AWS', 'sandbox' => 'Sandbox local']
                            : ['production' => 'Producao AWS'])
                        ->default('production')->required(),
                ])
                ->action(fn (array $data) => $this->startOperation('backup', $data['mode'])),
            Action::make('downloadBackup')
                ->label('Descarregar backup')
                ->color('success')
                ->visible(fn (): bool => $this->latestBackup() !== null)
                ->action(fn (): StreamedResponse => $this->downloadBackup()),
            Action::make('productionToSandbox')
                ->label('Copiar producao → sandbox')
                ->color('warning')
                ->visible(fn (): bool => (bool) config('database-management.local'))
                ->requiresConfirmation()
                ->modalDescription('Substitui os dados da sandbox local. Sera criado um backup do destino antes da copia.')
                ->action(fn () => $this->startOperation('copy', 'production', 'sandbox')),
            Action::make('sandboxToProduction')
                ->label('Copiar sandbox → producao')
                ->color('danger')
                ->visible(fn (): bool => (bool) config('database-management.local'))
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Substitui os dados reais da AWS. E criado um backup privado e os servicos sao suspensos durante a importacao. Escreva o nome da base: '.config('database.profiles.production.database'))
                ->schema([
                    TextInput::make('database')->label('Nome da base de producao')->required()
                        ->rules([fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            if ($value !== config('database.profiles.production.database')) {
                                $fail('O nome da base de producao nao corresponde.');
                            }
                        }]),
                ])
                ->action(fn () => $this->startOperation('copy', 'sandbox', 'production')),
            Action::make('toggleMode')
                ->label(fn (): string => 'Alternar para '.(config('database.mode') === 'production' ? 'sandbox' : 'producao AWS'))
                ->color('success')
                ->visible(fn (): bool => (bool) config('database-management.local'))
                ->requiresConfirmation()
                ->modalDescription('Valida a ligacao antes de alterar o modo. Em producao, as alteracoes nesta aplicacao afetam dados reais da AWS. Sera necessario iniciar sessao novamente.')
                ->action(fn () => $this->toggleDatabaseMode()),
            Action::make('optimizeClear')
                ->label(fn (): string => config('database-management.local') ? 'Limpar caches locais' : 'Limpar caches deste servidor AWS')
                ->color('gray')
                ->requiresConfirmation()
                ->action(fn () => $this->runOptimizeClear()),
            Action::make('optimizeAws')
                ->label('Limpar caches AWS')
                ->color('gray')
                ->visible(fn (): bool => (bool) config('database-management.local'))
                ->requiresConfirmation()
                ->modalDescription('Limpa as caches dos contentores web, worker e scheduler e reinicia worker e scheduler na AWS.')
                ->action(fn () => $this->startOperation('optimize-aws', 'production')),
        ];
    }

    protected function startOperation(string $type, string $source, ?string $target = null): void
    {
        $this->authorizeManagement($type === 'copy' || $type === 'optimize-aws');

        try {
            app(DatabaseManagementService::class)->enqueue($type, $source, $target);
            Notification::make()->info()->title('Operacao pendente')
                ->body('Acompanhe o resultado nesta pagina.')->send();
        } catch (Throwable $exception) {
            $this->notifyFailure($exception);
        }
    }

    /** @return array<string, mixed>|null */
    public function operation(): ?array
    {
        $this->authorizeManagement();

        $management = app(DatabaseManagementService::class);
        $management->recoverInterruptedOperation();

        return $management->latest();
    }

    protected function downloadBackup(): StreamedResponse
    {
        $this->authorizeManagement();
        $backup = $this->latestBackup();
        abort_unless(is_array($backup) && Storage::disk($backup['disk'])->exists($backup['path']), 404);

        return Storage::disk($backup['disk'])->download($backup['path'], basename($backup['path']), ['Content-Type' => 'application/gzip']);
    }

    /** @return array{disk: string, path: string}|null */
    public function latestBackup(): ?array
    {
        $this->authorizeManagement();

        return app(DatabaseManagementService::class)->read('latest-backup')['backup'] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function diagnostic(): ?array
    {
        $this->authorizeManagement();

        return app(DatabaseManagementService::class)->read('diagnostic');
    }

    protected function toggleDatabaseMode(): void
    {
        $this->authorizeManagement(true);

        try {
            app(DatabaseModeService::class)->switch(config('database.mode') === 'production' ? 'sandbox' : 'production');
            Filament::auth()->logout();
            session()->invalidate();
            session()->regenerateToken();
            $this->redirect(Filament::getLoginUrl());
        } catch (Throwable $exception) {
            $this->notifyFailure($exception);
        }
    }

    protected function runOptimizeClear(): void
    {
        $this->authorizeManagement();

        try {
            app(DatabaseManagementService::class)->exclusive(function (): void {
                if (Artisan::call('optimize:clear', ['--no-interaction' => true]) !== 0) {
                    throw new \RuntimeException('Nao foi possivel limpar as caches.');
                }
            });
            Notification::make()->success()->title('Caches deste ambiente limpas')->send();
        } catch (Throwable $exception) {
            $this->notifyFailure($exception);
        }
    }

    protected function notifyFailure(Throwable $exception): void
    {
        Notification::make()->danger()->title('Operacao nao concluida')
            ->body(app(DatabaseReplicationService::class)->safeError($exception))->send();
    }
}
