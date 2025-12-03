<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$console = $app->make(Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();

$taskId = \App\Models\Task::query()->orderBy('id')->value('id');

$component = Livewire\Livewire::test(\App\Filament\Pages\KanbanBoard::class);
$component->call('editTask', $taskId);

print_r($component->get('taskForm'));
