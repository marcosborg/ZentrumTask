<?php

namespace App\Filament\Resources\DriverMessageCampaigns\RelationManagers;

use App\Models\DriverMessageDelivery;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Histórico por motorista';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('driver_name')->label('Motorista')->searchable(),
                TextColumn::make('email_address')->label('Email')->placeholder('Sem email'),
                TextColumn::make('email_status')
                    ->label('Estado do email')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendente',
                        'sent' => 'Enviado',
                        'failed' => 'Falhou',
                        default => 'Indisponível',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('email_sent_at')->label('Email enviado em')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('email_error')
                    ->label('Erro do email')
                    ->limit(60)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('whatsapp_status')
                    ->label('WhatsApp')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'sent' ? 'Enviado' : ($state === 'pending' ? 'Por enviar' : 'Indisponível'))
                    ->color(fn (string $state): string => $state === 'sent' ? 'success' : ($state === 'pending' ? 'warning' : 'gray')),
                TextColumn::make('whatsapp_sent_at')
                    ->label('WhatsApp enviado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextColumn::make('whatsappSentBy.name')
                    ->label('WhatsApp registado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label(fn (DriverMessageDelivery $record): string => $record->whatsapp_status === 'sent' ? 'Reenviar WhatsApp' : 'Enviar WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('success')
                    ->url(fn (DriverMessageDelivery $record): string => route('driver-messages.whatsapp', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (DriverMessageDelivery $record): bool => $record->email_status === 'sent' && filled($record->phone_number)),
            ])
            ->headerActions([])
            ->bulkActions([])
            ->defaultSort('driver_name');
    }
}
