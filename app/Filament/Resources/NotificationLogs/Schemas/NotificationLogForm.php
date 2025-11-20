<?php

namespace App\Filament\Resources\NotificationLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class NotificationLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('notification_rule_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('task_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('to_email')
                    ->email()
                    ->required(),
                TextInput::make('subject')
                    ->default(null),
                TextInput::make('status')
                    ->required()
                    ->default('sent'),
                Textarea::make('error_message')
                    ->default(null)
                    ->columnSpanFull(),
                DateTimePicker::make('sent_at'),
            ]);
    }
}
