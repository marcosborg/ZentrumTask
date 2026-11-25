<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdvertisingProposalRequest;
use App\Mail\AdvertisingProposalReceived;
use App\Models\AdvertisingFormat;
use App\Models\Board;
use App\Models\CmsPage;
use App\Models\Task;
use App\Services\AndroidPushNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AdvertisingController extends Controller
{
    public function index(Request $request): View
    {
        \App\Support\AdvertisingAttribution::capture($request);

        $kit = $request->routeIs('ads.kit');

        return view('website.advertising', [
            'formats' => AdvertisingFormat::query()->where('active', true)->orderBy('id')->get(),
            'kit' => $kit,
            'editorial' => $kit ? CmsPage::query()->where('content_key', 'advertising_media_kit')->where('is_active', true)->first() : null,
        ]);
    }

    public function store(AdvertisingProposalRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['artwork', 'privacy', 'fax']);
        $format = AdvertisingFormat::query()->where('active', true)->findOrFail($data['format_id']);
        $reference = 'ads-'.hash('sha256', mb_strtolower($data['email']).'|'.$data['submission_key']);
        $file = $request->file('artwork');
        $disk = config('filesystems.advertising_disk');
        $path = $file?->store('advertising', $disk);
        abort_if($path === false, 503);

        try {
            $task = DB::transaction(function () use ($data, $request, $format, $file, $disk, $path, $reference): Task {
                $stage = Board::query()->where('slug', 'zentrum-ads')->where('is_active', true)
                    ->firstOrFail()->stages()->where('is_initial', true)->lockForUpdate()->firstOrFail();

                $existing = Task::query()->where('board_id', $stage->board_id)
                    ->where('external_reference', $reference)->first();
                if ($existing) {
                    return $existing;
                }

                $task = Task::query()->create([
                    'external_reference' => $reference,
                    'board_id' => $stage->board_id,
                    'stage_id' => $stage->id,
                    'title' => 'Publicidade · '.$data['company'],
                    'priority' => 'normal',
                    'position' => 1 + (int) $stage->tasks()->max('position'),
                    'description' => $this->campaignSummary($data, $format->name),
                    'meta' => [
                        ...$data,
                        'contact_name' => $data['name'],
                        'format' => $format->name,
                        'source' => 'zentrum_ads',
                        'utm' => $request->session()->get('ads_utm', []),
                        'privacy_read_at' => now()->toIso8601String(),
                    ],
                ]);

                if ($file) {
                    $task->attachments()->create([
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => basename($file->getClientOriginalName()),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }

                Mail::to($data['email'])->queue(new AdvertisingProposalReceived($data['name'], $task->id));

                return $task;
            });
        } catch (Throwable $error) {
            if ($path) {
                Storage::disk($disk)->delete($path);
            }
            throw $error;
        }

        if ($path && ! $task->wasRecentlyCreated) {
            Storage::disk($disk)->delete($path);
        }
        if ($task->wasRecentlyCreated && app()->isProduction()) {
            app(AndroidPushNotificationService::class)->sendNewContactTask($task);
        }

        return redirect()->route('ads.thanks')->with('ads_received', true);
    }

    private function campaignSummary(array $data, string $format): string
    {
        return collect([
            'Contacto' => $data['name'],
            'Email' => $data['email'],
            'Telefone' => $data['phone'],
            'Website' => $data['website'] ?? null,
            'Setor' => $data['sector'],
            'Campanha' => $data['campaign'],
            'Formato' => $format,
            'Viaturas' => $data['fleet'],
            'Regiões' => $data['regions'],
            'Duração' => $data['custom_duration'] ?? $data['duration'],
            'Início' => $data['start'] ?? null,
            'Orçamento indicativo (€)' => $data['budget'] ?? null,
            'Design' => $data['design'],
            'Mensagem' => $data['message'] ?? null,
        ])->filter(fn ($value): bool => filled($value))
            ->map(fn ($value, string $label): string => $label.': '.$value)
            ->implode("\n");
    }
}
