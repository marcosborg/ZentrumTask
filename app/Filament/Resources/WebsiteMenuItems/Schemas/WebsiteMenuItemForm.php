<?php

namespace App\Filament\Resources\WebsiteMenuItems\Schemas;

use App\Models\WebsiteMenuItem;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WebsiteMenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255),
                Select::make('parent_id')
                    ->label('Item pai (opcional)')
                    ->options(fn (?WebsiteMenuItem $record) => WebsiteMenuItem::query()
                        ->whereNull('parent_id')
                        ->when($record?->exists, fn ($query) => $query->where('id', '!=', $record->getKey()))
                        ->orderBy('label')
                        ->pluck('label', 'id'))
                    ->placeholder('Sem pai')
                    ->searchable()
                    ->columnSpan(1),
                TextInput::make('url')
                    ->label('URL')
                    ->placeholder('Opcional para grupos sem link')
                    ->maxLength(255)
                    ->columnSpan(1),
            ]);
    }
}
