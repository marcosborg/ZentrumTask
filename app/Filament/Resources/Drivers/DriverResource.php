<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Resources\Drivers\Pages\CreateDriver;
use App\Filament\Resources\Drivers\Pages\EditDriver;
use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Filament\Resources\Drivers\Tables\DriversTable;
use App\Models\Driver;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identificacao')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Nome')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Telefone')
                            ->maxLength(50),
                        TextInput::make('nif')
                            ->label('NIF')
                            ->maxLength(50),
                        DatePicker::make('date_of_birth')
                            ->label('Data de nascimento'),
                        TextInput::make('nationality')
                            ->label('Nacionalidade')
                            ->maxLength(255),
                        TextInput::make('marital_status')
                            ->label('Estado civil')
                            ->maxLength(255),
                        Textarea::make('address')
                            ->label('Morada')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),
                Section::make('Documento de Identificacao')
                    ->columns(2)
                    ->components([
                        TextInput::make('identity_document_type')
                            ->label('Tipo de documento')
                            ->maxLength(255),
                        TextInput::make('identity_document_number')
                            ->label('Numero do documento')
                            ->maxLength(255),
                        DatePicker::make('identity_document_expires_at')
                            ->label('Validade do documento'),
                    ]),
                Section::make('Carta de Conducao')
                    ->columns(2)
                    ->components([
                        TextInput::make('license_number')
                            ->label('Numero da carta')
                            ->maxLength(100),
                        DatePicker::make('license_issued_at')
                            ->label('Emitida em'),
                        DatePicker::make('license_expires_at')
                            ->label('Validade'),
                        TextInput::make('license_category')
                            ->label('Categoria')
                            ->maxLength(255),
                    ]),
                Section::make('TVDE')
                    ->columns(2)
                    ->components([
                        TextInput::make('tvde_certificate_number')
                            ->label('Numero certificado TVDE')
                            ->maxLength(255),
                        DatePicker::make('tvde_certificate_expires_at')
                            ->label('Validade certificado'),
                        CheckboxList::make('tvde_platforms')
                            ->label('Plataformas')
                            ->options([
                                'uber' => 'Uber',
                                'bolt' => 'Bolt',
                                'free_now' => 'FREE NOW',
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Contacto de Emergencia')
                    ->columns(2)
                    ->components([
                        TextInput::make('emergency_contact_name')
                            ->label('Nome do contacto')
                            ->maxLength(255),
                        TextInput::make('emergency_contact_phone')
                            ->label('Telefone do contacto')
                            ->maxLength(255),
                    ]),
                Section::make('Dados Bancarios')
                    ->columns(2)
                    ->components([
                        TextInput::make('bank_account_holder')
                            ->label('Titular da conta')
                            ->maxLength(255),
                        TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(34),
                    ]),
                Section::make('Caucao')
                    ->columns(3)
                    ->components([
                        TextInput::make('deposit_amount')
                            ->label('Valor caucao')
                            ->numeric()
                            ->step('0.01'),
                        DatePicker::make('deposit_paid_at')
                            ->label('Pago em'),
                        TextInput::make('deposit_payment_method')
                            ->label('Metodo de pagamento')
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return DriversTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BillingProfilesRelationManager::class,
            RelationManagers\WeekStatementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
            'create' => CreateDriver::route('/create'),
            'edit' => EditDriver::route('/{record}/edit'),
        ];
    }
}
