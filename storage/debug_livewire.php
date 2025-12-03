<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

$console = $app->make(Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();

$taskId = \App\Models\Task::query()->orderBy('id')->value('id');

echo "Task ID: {$taskId}\n";

$component = Livewire\Livewire::test(\App\Filament\Pages\KanbanBoard::class);

$component->set('boardId', \App\Models\Task::find($taskId)?->board_id);
$component->call('loadBoard');
$component->call('editTask', $taskId);

print_r($component->get('taskForm'));
