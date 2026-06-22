<?php

use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createChatLeadBoard(): void
{
    $board = Board::query()->create([
        'name' => 'Leads',
        'slug' => 'leads',
        'is_active' => true,
        'position' => 1,
    ]);

    Stage::query()->create([
        'board_id' => $board->id,
        'name' => 'Entrada',
        'slug' => 'entrada',
        'position' => 1,
        'is_initial' => true,
        'is_final' => false,
        'freeze_sla' => false,
    ]);
}

it('does not use a chat id or session token as the contact phone', function () {
    createChatLeadBoard();

    $session = $this->postJson(route('website.chat.session'), [])
        ->assertOk()
        ->json('session_token');

    $response = $this->postJson(route('website.chat.message'), [
        'session_token' => $session,
        'message' => 'Quero alugar uma viatura. Sou Joao Silva. O meu telefone e '.$session,
    ]);

    $response->assertOk()
        ->assertJsonPath('assistant_message.role', 'assistant');

    expect(Task::query()->count())->toBe(0)
        ->and($response->json('assistant_message.content'))->toContain('telefone');
});

it('creates a website chat kanban lead only after collecting name and phone', function () {
    createChatLeadBoard();

    $session = $this->postJson(route('website.chat.session'), [])
        ->assertOk()
        ->json('session_token');

    $this->postJson(route('website.chat.message'), [
        'session_token' => $session,
        'message' => 'Quero saber condicoes para alugar uma viatura. Sou Maria Costa.',
    ])->assertOk();

    expect(Task::query()->count())->toBe(0);

    $this->postJson(route('website.chat.message'), [
        'session_token' => $session,
        'message' => 'O meu telefone e 912 345 678 e o email e maria@example.com.',
    ])->assertOk();

    $task = Task::query()->firstOrFail();

    expect($task->title)->toStartWith('Chat website: Maria Costa')
        ->and($task->external_reference)->toBe('website-chat-1')
        ->and($task->meta['source'])->toBe('website')
        ->and($task->meta['contact_name'])->toBe('Maria Costa')
        ->and($task->meta['phone'])->toBe('912 345 678')
        ->and($task->meta['email'])->toBe('maria@example.com')
        ->and($task->meta['chat_session_token'])->toBe($session);
});
