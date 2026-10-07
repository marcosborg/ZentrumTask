<?php

namespace App\Filament\Resources\SlotPacks;

use App\Filament\Resources\SlotPacks\Pages\ManageSlotPacks;
use App\Filament\Resources\SlotResource;
use App\Models\SlotPack;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SlotPackResource extends SlotResource
{
    protected static ?string $model = SlotPack::class;

    protected static ?string $navigationLabel = 'Packs SLOT';

    protected static ?string $modelLabel = 'Packs SLOT';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('code')->label('Pack')->options(['base' => 'Base', 'premium' => 'Premium'])->required(),
            TextInput::make('name')->label('Nome')->required(),
            TextInput::make('weekly_price')->label('Preço semanal (IVA incluído)')->numeric()->minValue(0)->required(),
            DatePicker::make('valid_from')->label('Válido desde')->required(),
            DatePicker::make('valid_to')->label('Válido até')->afterOrEqual('valid_from'),
            Textarea::make('benefits')->label('Benefícios')->required()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Pack'),
            TextColumn::make('weekly_price')->label('Taxa semanal')->money('EUR'),
            TextColumn::make('valid_from')->label('Desde')->date('d/m/Y'),
            TextColumn::make('valid_to')->label('Até')->date('d/m/Y'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSlotPacks::route('/')];
    }
}
