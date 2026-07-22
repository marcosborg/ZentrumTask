<?php

namespace App\Filament\Resources\DriverMessageCampaigns\Schemas;

use App\Models\Driver;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DriverMessageCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mensagem')
                    ->description('O email é enviado individualmente a cada motorista selecionado.')
                    ->components([
                        TextInput::make('subject')->label('Assunto')->required()->maxLength(255),
                        Textarea::make('body')->label('Mensagem')->required()->rows(10)->columnSpanFull(),
                    ]),
                Section::make('Motoristas')
                    ->description('Pode selecionar um, vários ou usar “Selecionar todos”. Contactos em falta ficam registados como indisponíveis.')
                    ->components([
                        CheckboxList::make('driver_ids')
                            ->label('Destinatários')
                            ->options(fn (): array => Driver::query()
                                ->orderBy('name')
                                ->get(['id', 'name', 'email', 'phone'])
                                ->mapWithKeys(fn (Driver $driver): array => [
                                    $driver->id => collect([$driver->name, $driver->email ?: 'sem email', $driver->phone ?: 'sem telefone'])->implode(' — '),
                                ])->all())
                            ->searchable()
                            ->bulkToggleable()
                            ->required()
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
