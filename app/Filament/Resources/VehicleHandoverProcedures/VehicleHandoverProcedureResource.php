<?php

namespace App\Filament\Resources\VehicleHandoverProcedures;

use App\Filament\Resources\VehicleHandoverProcedures\Pages\CreateVehicleHandoverProcedure;
use App\Filament\Resources\VehicleHandoverProcedures\Pages\EditVehicleHandoverProcedure;
use App\Filament\Resources\VehicleHandoverProcedures\Pages\ListVehicleHandoverProcedures;
use App\Filament\Resources\VehicleHandoverProcedures\Pages\ViewVehicleHandoverProcedure;
use App\Filament\Resources\VehicleHandoverProcedures\Schemas\VehicleHandoverProcedureForm;
use App\Filament\Resources\VehicleHandoverProcedures\Schemas\VehicleHandoverProcedureInfolist;
use App\Filament\Resources\VehicleHandoverProcedures\Tables\VehicleHandoverProceduresTable;
use App\Models\VehicleHandoverProcedure;
use App\Services\VehicleHandoverProcedureService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UnitEnum;

use function Filament\Support\original_request;

class VehicleHandoverProcedureResource extends Resource
{
    protected static ?string $model = VehicleHandoverProcedure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE Aluguer';

    protected static ?int $navigationSort = 35;

    protected static ?string $navigationLabel = 'Entregas & Devolucoes';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        $user = Filament::auth()->user();

        return (int) $user?->getAuthIdentifier() === 1
            && $user?->email === 'marcosborges@netlook.pt'
                ? Response::allow()
                : Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()->label('Eliminar')
            ->modalDescription(fn (VehicleHandoverProcedure $record): string => $record->type === 'return' && $record->status === 'completed'
                ? 'Eliminar esta recolha e repor a alocação anterior? A operação será bloqueada se existirem movimentos incompatíveis.'
                : 'Eliminar este auto definitivamente? As alocações não serão alteradas.')
            ->using(function (VehicleHandoverProcedure $record): bool {
                abort_unless(static::canDelete($record), 403);

                try {
                    return app(VehicleHandoverProcedureService::class)->deleteWithAllocationRestoration($record);
                } catch (ValidationException $exception) {
                    Notification::make()->title('Eliminação bloqueada')->body($exception->getMessage())->danger()->send();

                    return false;
                }
            });
    }

    public static function form(Schema $schema): Schema
    {
        return VehicleHandoverProcedureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleHandoverProceduresTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VehicleHandoverProcedureInfolist::configure($schema);
    }

    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();
        $items[0]->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteBaseName().'.*') && ! original_request()->routeIs(static::getRouteBaseName().'.inspections'));
        $items[] = NavigationItem::make('Inspeções')
            ->group(static::getNavigationGroup())
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->sort(36)
            ->url(static::getUrl('inspections'))
            ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteBaseName().'.inspections'));

        return $items;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleHandoverProcedures::route('/'),
            'create' => CreateVehicleHandoverProcedure::route('/create'),
            'inspections' => Pages\ListVehicleInspections::route('/inspections'),
            'view' => ViewVehicleHandoverProcedure::route('/{record}'),
            'edit' => EditVehicleHandoverProcedure::route('/{record}/edit'),
        ];
    }
}
