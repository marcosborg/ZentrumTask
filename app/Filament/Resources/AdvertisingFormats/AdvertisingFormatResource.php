<?php

namespace App\Filament\Resources\AdvertisingFormats;

use App\Filament\Resources\AdvertisingFormats\Pages\ManageAdvertisingFormats;
use App\Models\AdvertisingFormat;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdvertisingFormatResource extends Resource
{
    protected static ?string $model = AdvertisingFormat::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Zentrum Ads';

    protected static ?string $modelLabel = 'formato publicitário';

    protected static ?string $pluralModelLabel = 'formatos publicitários';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)->unique(ignoreRecord: true),
                \Filament\Forms\Components\Textarea::make('description')->label('Descrição')->required()->maxLength(1500),
                \Filament\Forms\Components\Select::make('image')->label('Exemplo visual')->options(['city' => 'City', 'urban' => 'Urban', 'impact' => 'Impact']),
                \Filament\Forms\Components\Toggle::make('active')->label('Disponível para propostas')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdvertisingFormats::route('/'),
        ];
    }
}
