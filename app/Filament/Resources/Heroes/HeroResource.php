<?php

namespace App\Filament\Resources\Heroes;

use App\Filament\Resources\Heroes\Pages\CreateHero;
use App\Filament\Resources\Heroes\Pages\EditHero;
use App\Filament\Resources\Heroes\Pages\ListHeroes;
use App\Filament\Resources\Heroes\Schemas\HeroForm;
use App\Filament\Resources\Heroes\Tables\HeroesTable;
use App\Models\Hero;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HeroResource extends Resource
{
    protected static ?string $model = Hero::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'title';

    protected static UnitEnum|string|null $navigationGroup = 'Website';

    public static function form(Schema $schema): Schema
    {
        return HeroForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HeroesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHeroes::route('/'),
            'create' => CreateHero::route('/create'),
            'edit' => EditHero::route('/{record}/edit'),
        ];
    }
}
