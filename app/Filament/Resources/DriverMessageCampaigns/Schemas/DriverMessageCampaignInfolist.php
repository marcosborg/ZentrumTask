<?php

namespace App\Filament\Resources\DriverMessageCampaigns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DriverMessageCampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mensagem enviada')
                    ->columns(2)
                    ->components([
                        TextEntry::make('subject')->label('Assunto')->columnSpanFull(),
                        TextEntry::make('body')->label('Mensagem')->columnSpanFull(),
                        TextEntry::make('createdBy.name')->label('Criada por')->placeholder('Utilizador removido'),
                        TextEntry::make('created_at')->label('Data')->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
