<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Services\BoltPlatformCsvImportService;
use App\Services\UberPlatformCsvImportService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use UnitEnum;

class PlatformImports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?string $navigationLabel = 'Imports';

    protected static ?string $title = 'Imports de plataformas';

    protected string $view = 'filament.pages.platform-imports';

    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    /** @var list<string> */
    public array $missingDriverCodes = [];

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->form->fill([
            'platform' => null,
            'file' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->schema([
                Select::make('platform')
                    ->label('Plataforma')
                    ->options([
                        'bolt' => 'Bolt',
                        'uber' => 'Uber',
                    ])
                    ->required()
                    ->native(false),
                FileUpload::make('file')
                    ->label('CSV')
                    ->disk('local')
                    ->directory('platform-imports')
                    ->preserveFilenames()
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                    ])
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('period_start')
                    ->label('Periodo inicio (opcional)')
                    ->native(false),
                DatePicker::make('period_end')
                    ->label('Periodo fim (opcional)')
                    ->native(false),
            ])
            ->statePath('data');
    }

    public function runImport(): void
    {
        $this->errorMessage = null;
        $this->result = null;
        $this->missingDriverCodes = [];

        $data = $this->form->getState();
        $platform = $data['platform'] ?? null;
        $file = $data['file'] ?? null;
        $periodStart = $data['period_start'] ?? null;
        $periodEnd = $data['period_end'] ?? null;

        if (! $platform || ! $file) {
            Notification::make()
                ->danger()
                ->title('Selecione a plataforma e o ficheiro CSV')
                ->send();

            return;
        }

        if (($periodStart && ! $periodEnd) || ($periodEnd && ! $periodStart)) {
            Notification::make()
                ->danger()
                ->title('Indique inicio e fim do periodo')
                ->send();

            return;
        }

        $path = Storage::disk('local')->path($file);
        $options = [];

        if ($periodStart && $periodEnd) {
            $options = [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ];
        }

        try {
            $result = $platform === 'bolt'
                ? app(BoltPlatformCsvImportService::class)->import($path, $options)
                : app(UberPlatformCsvImportService::class)->import($path, $options);

            $driverCodes = array_values(array_filter(array_map(
                fn (string $code): string => strtolower(trim($code)),
                $result['driver_codes'] ?? []
            )));

            if ($driverCodes !== []) {
                $column = $platform === 'bolt' ? 'bolt_driver_code' : 'uber_driver_code';
                $normalizedColumn = DB::raw("LOWER(TRIM({$column}))");

                $missing = Driver::query()
                    ->whereNotNull($column)
                    ->whereIn($normalizedColumn, $driverCodes)
                    ->selectRaw("LOWER(TRIM({$column})) as code")
                    ->pluck('code')
                    ->filter()
                    ->all();

                $this->missingDriverCodes = array_values(array_diff($driverCodes, $missing));
            }

            $this->result = [
                'total' => $result['total'] ?? 0,
                'inserted' => $result['inserted'] ?? 0,
                'skipped' => $result['skipped'] ?? 0,
                'duplicates' => $result['duplicates'] ?? 0,
                'invalid_rows' => $result['invalid_rows'] ?? 0,
                'period_start' => $result['period_start'] ?? null,
                'period_end' => $result['period_end'] ?? null,
                'platform' => $platform,
            ];

            Notification::make()
                ->success()
                ->title('Import concluido')
                ->send();
        } catch (RuntimeException $exception) {
            $this->errorMessage = $exception->getMessage();

            Notification::make()
                ->danger()
                ->title('Falha no import')
                ->body($exception->getMessage())
                ->send();
        }
    }
}
