<?php

use App\Mail\AdvertisingProposalReceived;
use App\Models\AdvertisingFormat;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function advertisingProposalData(): array
{
    return ['submission_key' => (string) \Illuminate\Support\Str::uuid(), 'company' => 'Marca de teste', 'name' => 'Contacto', 'email' => 'ads@example.test', 'phone' => '910000000', 'sector' => 'Comércio', 'campaign' => 'Abertura', 'fleet' => '1 a 5', 'regions' => 'Porto, Matosinhos', 'format_id' => AdvertisingFormat::firstOrFail()->id, 'duration' => '1 mês', 'design' => 'Sim', 'privacy' => '1'];
}

it('mostra o percurso público e os formatos ativos', function (): void {
    AdvertisingFormat::whereName('Interior')->update(['active' => false]);
    $this->get('/publicidade')->assertOk()->assertSee('Configure a sua campanha')->assertDontSee('Suportes físicos');
    $this->get('/publicidade/media-kit')->assertOk()->assertSee('Imprimir')->assertDontSee('ads-form', false);
    $this->get('/')->assertOk()->assertSee('Quero anunciar');
    $this->get('/slot?interesse=publicidade')->assertOk()->assertSee('Gestão de publicidade');
});

it('regista o pedido, a origem, o anexo privado e a confirmação', function (): void {
    Mail::fake();
    Storage::fake('local');
    $this->get('/?utm_source=feira');
    $this->get('/publicidade');
    $this->post(route('ads.store'), [...advertisingProposalData(), 'artwork' => UploadedFile::fake()->image('marca.jpg')])->assertSessionHasNoErrors()->assertRedirect(route('ads.thanks'));
    $task = Task::where('meta->source', 'zentrum_ads')->sole();
    expect($task->stage->name)->toBe('Novo')->and($task->meta['utm']['utm_source'])->toBe('feira');
    $file = $task->attachments()->sole();
    Storage::disk('local')->assertExists($file->path);
    $this->get(route('ads.attachment', $file))->assertRedirect();
    $this->actingAs(User::factory()->create())->get(route('ads.attachment', $file))->assertDownload('marca.jpg');
    Mail::assertQueued(AdvertisingProposalReceived::class, fn ($mail): bool => $mail->hasTo('ads@example.test'));
});

it('recusa dados inválidos e anexos não permitidos', function (): void {
    Mail::fake();
    $format = AdvertisingFormat::factory()->create(['active' => false]);
    $this->post(route('ads.store'), [...advertisingProposalData(), 'privacy' => '0', 'fax' => 'spam', 'format_id' => $format->id, 'duration' => 'Personalizada', 'artwork' => UploadedFile::fake()->create('teste.html', 1, 'text/html')])->assertSessionHasErrors(['privacy', 'fax', 'format_id', 'custom_duration', 'artwork']);
    expect(Task::count())->toBe(0);
    Mail::assertNothingQueued();
});

it('regista interesse no SLOT Premium sem aprovar automaticamente uma viatura', function (): void {
    $this->mock(\App\Services\AndroidPushNotificationService::class)->shouldReceive('sendNewContactTask')->once();
    $this->post(route('contact.submit'), [
        'source' => 'website_slot', 'slot_pack' => 'premium', 'ads_interest' => 1,
        'name' => 'Motorista', 'email' => 'motorista@example.test', 'phone' => '910000000',
        'message' => 'Tenho interesse em campanhas.',
    ])->assertSessionHasNoErrors()->assertSessionHas('ads_interest_received', true);
    $task = Task::sole();
    expect($task->board->slug)->toBe('zentrum-ads')->and($task->meta['ads_interest'])->toBeTrue();
});

it('permite à equipa criar uma oportunidade e registar um contacto no painel', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $stage = \App\Models\Board::whereSlug('zentrum-ads')->firstOrFail()->stages()->firstOrFail();
    $page = \Livewire\Livewire::test(\App\Filament\Resources\AdvertisingOpportunities\Pages\ManageAdvertisingOpportunities::class);
    $page->callAction('create', ['title' => 'Empresa local', 'stage_id' => $stage->id, 'assigned_to_id' => $user->id])
        ->assertHasNoActionErrors();
    $task = Task::whereTitle('Empresa local')->sole();
    $page->callAction(\Filament\Actions\Testing\TestAction::make('note')->table($task), ['body' => 'Contacto telefónico'])
        ->assertHasNoActionErrors();
    expect($task->comments()->sole()->body)->toBe('Contacto telefónico');
    $page->callAction('export')->assertFileDownloaded('zentrum-ads.csv');
});

it('trata uma repetição do mesmo pedido sem duplicar a oportunidade ou o email', function (): void {
    Mail::fake();
    $this->get('/publicidade');
    $data = advertisingProposalData();
    $this->post(route('ads.store'), $data)->assertRedirect(route('ads.thanks'));
    $this->post(route('ads.store'), $data)->assertRedirect(route('ads.thanks'));
    expect(Task::count())->toBe(1);
    Mail::assertQueued(AdvertisingProposalReceived::class, 1);
});

it('limita tentativas repetidas de submissão', function (): void {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('ads.store'), [])->assertSessionHasErrors('company');
    }
    $this->post(route('ads.store'), [])->assertTooManyRequests();
});

it('protege o acesso às oportunidades e ao catálogo', function (): void {
    $this->get('/admin/advertising-opportunities')->assertRedirect();
    $this->actingAs(User::factory()->create())->get('/admin/advertising-opportunities')->assertOk();
    $this->get('/admin/advertising-formats')->assertOk();
});
