<?php

namespace App\Filament\Resources\DriverBillingProfiles;

use App\Filament\Resources\DriverBillingProfiles\Pages\CreateDriverBillingProfile;
use App\Filament\Resources\DriverBillingProfiles\Pages\EditDriverBillingProfile;
use App\Filament\Resources\DriverBillingProfiles\Pages\ListDriverBillingProfiles;
use App\Filament\Resources\DriverBillingProfiles\Schemas\DriverBillingProfileForm;
use App\Filament\Resources\DriverBillingProfiles\Tables\DriverBillingProfilesTable;
use App\Models\DriverBillingProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DriverBillingProfileResource extends Resource
{
    protected static ?string $model = DriverBillingProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return DriverBillingProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriverBillingProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverBillingProfiles::route('/'),
            'create' => CreateDriverBillingProfile::route('/create'),
            'edit' => EditDriverBillingProfile::route('/{record}/edit'),
        ];
    }
}
