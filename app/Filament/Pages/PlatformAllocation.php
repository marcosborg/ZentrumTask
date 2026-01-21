<?php

namespace App\Filament\Pages;

use App\Services\PlatformDriverBalanceAllocator;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RuntimeException;
use UnitEnum;

class PlatformAllocation extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?string $navigationLabel = 'Alocacao de balances';

    protected static ?string $title = 'Alocacao de balances';

    protected string $view = 'filament.pages.platform-allocation';

    public ?array $data = [];

    /** @var array<string, int>|null */
    public ?array $result = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->form->fill([
            'platform' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Select::make('platform')
                    ->label('Plataforma (opcional)')
                    ->options([
                        'bolt' => 'Bolt',
                        'uber' => 'Uber',
                    ])
                    ->native(false)
                    ->placeholder('Todas'),
            ])
            ->statePath('data');
    }

    public function runAllocation(): void
    {
        $this->errorMessage = null;
        $this->result = null;

        $data = $this->form->getState();
        $platform = $data['platform'] ?? null;

        try {
            $result = app(PlatformDriverBalanceAllocator::class)->allocate($platform);

            $this->result = [
                'allocated' => $result['allocated'] ?? 0,
                'pending' => $result['pending'] ?? 0,
            ];

            Notification::make()
                ->success()
                ->title('Alocacao concluida')
                ->send();
        } catch (RuntimeException $exception) {
            $this->errorMessage = $exception->getMessage();

            Notification::make()
                ->danger()
                ->title('Falha na alocacao')
                ->body($exception->getMessage())
                ->send();
        }
    }
}
