<?php

namespace App\Filament\Resources\NotificationRules\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class NotificationRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stage_id')
                    ->relationship('stage', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('message_template_id')
                    ->relationship('messageTemplate', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('recipient_list_id')
                    ->relationship('recipientList', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('trigger')
                    ->options([
                        'on_enter_stage' => 'On Enter Stage',
                        'on_exit_stage' => 'On Exit Stage',
                        'on_task_update' => 'On Task Update',
                    ])
                    ->required()
                    ->label('Trigger')
                    ->default('on_enter_stage'),
                Select::make('send_mode')
                    ->label('Send Mode')
                    ->required()
                    ->options([
                        'always' => 'Always',
                        'only_if_no_response' => 'Only If No Response',
                    ])
                    ->default('always'),
                TextInput::make('cooldown_hours')
                    ->numeric()
                    ->default(null),
                Toggle::make('also_send_to_assigned_user')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
