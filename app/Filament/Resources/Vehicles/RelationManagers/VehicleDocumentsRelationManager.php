<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Models\VehicleDocument;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehicleDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'DUA' => 'DUA',
                        'INSURANCE' => 'Seguro',
                        'GREEN_CARD' => 'Carta Verde',
                        'INSPECTION' => 'Inspecao',
                        'OTHER' => 'Outro',
                    ])
                    ->required()
                    ->native(false),
                TextInput::make('title')
                    ->label('Titulo')
                    ->required()
                    ->maxLength(255),
                TextInput::make('document_number')
                    ->label('Numero')
                    ->maxLength(255),
                TextInput::make('issuer')
                    ->label('Emissor')
                    ->maxLength(255),
                DatePicker::make('issue_date')
                    ->label('Data de emissao')
                    ->native(false),
                DatePicker::make('expires_at')
                    ->label('Validade')
                    ->native(false),
                SpatieMediaLibraryFileUpload::make('document')
                    ->label('Ficheiro')
                    ->collection('document')
                    ->downloadable()
                    ->openable()
                    ->preserveFilenames()
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Titulo')
                    ->searchable(),
                TextColumn::make('issuer')
                    ->label('Emissor')
                    ->toggleable(),
                TextColumn::make('expires_at')
                    ->label('Validade')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'valid',
                        'warning' => ['expiring_7', 'expiring_30'],
                        'danger' => 'expired',
                        'gray' => 'no_expiry',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'valid' => 'Valido',
                        'expiring_7' => 'Expira em 7 dias',
                        'expiring_30' => 'Expira em 30 dias',
                        'expired' => 'Expirado',
                        'no_expiry' => 'Sem validade',
                        default => $state,
                    }),
                TextColumn::make('file')
                    ->label('Ficheiro')
                    ->formatStateUsing(fn (?string $state, VehicleDocument $record): string => $record->getFirstMedia('document')?->file_name ?? '-')
                    ->url(fn (VehicleDocument $record): ?string => $record->getFirstMediaUrl('document') ?: null)
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('expires_at');
    }
}
