<?php

namespace App\Filament\Resources\DriverSettlements;

use App\Enums\TvdeOperation;
use App\Filament\Resources\DriverSettlements\Pages\ManageDriverSettlements;
use App\Filament\Resources\SlotResource;
use App\Mail\SlotSettlementSummaryMail;
use App\Models\DriverSettlement;
use App\Services\SlotSettlementPaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class DriverSettlementResource extends SlotResource
{
    protected static ?string $model = DriverSettlement::class;

    protected static ?string $navigationLabel = 'Settlements SLOT';

    protected static ?string $modelLabel = 'settlement SLOT';

    protected static ?string $pluralModelLabel = 'Settlements SLOT';

    protected static ?string $slug = 'slot-settlements';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->forOperation(TvdeOperation::Slot)->with(['driver', 'participation.balance']);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('driver.name')->label('Motorista')->searchable(),
            TextColumn::make('period_start')->label('Semana')->date('d/m/Y')->sortable(),
            TextColumn::make('rules_snapshot.slot_pack_name')->label('Pack'),
            TextColumn::make('net_total')->label('Ganhos (inclui gorjetas)')->money('EUR'),
            TextColumn::make('tips_total')->label('Gorjetas')->money('EUR')->toggleable(),
            TextColumn::make('rules_snapshot.vat_amount')->label('IVA motorista')->money('EUR')->toggleable(),
            TextColumn::make('rules_snapshot.withholding_amount')->label('Retenção')->money('EUR')->toggleable(),
            TextColumn::make('expenses_total')->label('Ajustes')->money('EUR'),
            TextColumn::make('slot_fee')->label('Pack (IVA incluído)')->money('EUR'),
            TextColumn::make('amount_payable')->label('Valor da semana')->money('EUR'),
            TextColumn::make('carry_over_balance')->label('Saldo anterior')->money('EUR')->toggleable(),
            TextColumn::make('amount_transferred')->label('Transferido')->money('EUR'),
            TextColumn::make('participation.balance.current_balance')->label('Saldo da participação')->money('EUR'),
            TextColumn::make('payment_due_at')->label('Segunda-feira de pagamento')->date('d/m/Y'),
            TextColumn::make('payment_state')->label('Pagamento')->state(fn (DriverSettlement $record) => $record->is_paid ? 'Pago' : ((! $record->platform_received_at || ! $record->reconciled_at) ? 'Pendente de recebimento / reconciliação' : 'Pronto')),
            TextColumn::make('payment_delay_reason')->label('Motivo do atraso')->toggleable(),
        ])->filters([
            SelectFilter::make('is_paid')->label('Pagamento')->options(['0' => 'Pendente', '1' => 'Pago']),
            Filter::make('period')->schema([DatePicker::make('start')->label('Desde'), DatePicker::make('end')->label('Até')])->query(fn (Builder $query, array $data) => $query->when($data['start'] ?? null, fn ($q, $date) => $q->whereDate('period_start', '>=', $date))->when($data['end'] ?? null, fn ($q, $date) => $q->whereDate('period_end', '<=', $date))),
        ])->recordActions([
            Action::make('confirmFunds')->label('Confirmar fundos')->visible(fn (DriverSettlement $record) => ! $record->is_paid)->schema([
                Checkbox::make('received')->label('Valores recebidos')->accepted(),
                Checkbox::make('reconciled')->label('Semana reconciliada')->accepted(),
            ])->action(fn (DriverSettlement $record) => $record->update(['platform_received_at' => now(), 'reconciled_at' => now(), 'payment_delay_reason' => null])),
            Action::make('delay')->label('Registar atraso')->schema([Textarea::make('reason')->label('Motivo')->required()])->action(fn (DriverSettlement $record, array $data) => $record->update(['payment_delay_reason' => $data['reason']])),
            Action::make('pay')->label('Confirmar pagamento')->visible(fn (DriverSettlement $record) => ! $record->is_paid)->schema([
                \Filament\Forms\Components\Hidden::make('payment_reference')->default(fn () => (string) \Illuminate\Support\Str::uuid())->required(),
                TextInput::make('amount')->label('Valor efetivamente transferido')->numeric()->minValue(0.01)->required(),
            ])->action(fn (DriverSettlement $record, array $data) => app(SlotSettlementPaymentService::class)->pay($record, (float) $data['amount'], $data['payment_reference'])),
            Action::make('receipt')->label('Recibo')->schema([
                FileUpload::make('path')->label('Recibo verde')->disk(config('filesystems.settlement_receipts_disk', 'local'))->directory('slot-receipts')->visibility('private')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->required(),
            ])->action(fn (DriverSettlement $record, array $data) => $record->update(['green_receipt_path' => $data['path'], 'green_receipt_uploaded_at' => now(), 'green_receipt_uploaded_by_user_id' => auth()->id()])),
            Action::make('downloadReceipt')->label('Descarregar recibo')->visible(fn (DriverSettlement $record) => (bool) $record->green_receipt_path)->url(fn (DriverSettlement $record) => route('driver-settlements.green-receipt.download', $record))->openUrlInNewTab(),
            Action::make('email')->label('Enviar extrato')->requiresConfirmation()->action(function (DriverSettlement $record): void {
                Mail::to($record->driver->email)->send(new SlotSettlementSummaryMail($record));
                $record->update(['email_sent_count' => $record->email_sent_count + 1, 'last_emailed_at' => now(), 'last_emailed_to' => $record->driver->email]);
                $record->emailLogs()->create(['driver_id' => $record->driver_id, 'recipient' => $record->driver->email, 'triggered_by_user_id' => auth()->id(), 'status' => 'sent']);
            }),
        ])->headerActions([
            Action::make('export')->label('Exportar lista')->action(function ($livewire) {
                $rows = $livewire->getFilteredTableQuery()->forOperation(TvdeOperation::Slot)->get();

                return response()->streamDownload(function () use ($rows): void {
                    $stream = fopen('php://output', 'w');
                    fputcsv($stream, ['Motorista', 'Participação', 'Início', 'Fim', 'Pack', 'Taxa IVA incluído', 'Valor semana', 'Transferido']);
                    foreach ($rows as $row) {
                        $name = preg_match('/^[=+@-]/', $row->driver->name) ? "'".$row->driver->name : $row->driver->name;
                        fputcsv($stream, [$name, $row->driver_participation_id, $row->period_start->toDateString(), $row->period_end->toDateString(), $row->rules_snapshot['slot_pack_name'], $row->slot_fee, $row->amount_payable, $row->amount_transferred]);
                    }
                    fclose($stream);
                }, 'settlements-slot.csv');
            }),
        ])->defaultSort('period_start', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ManageDriverSettlements::route('/')];
    }
}
