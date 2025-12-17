<?php

namespace App\Filament\Resources\MessageTemplates\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('subject')
                    ->required(),
                RichEditor::make('body')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_html')
                    ->required(),
            ]);
    }
}
