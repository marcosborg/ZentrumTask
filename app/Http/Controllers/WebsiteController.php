<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactTaskRequest;
use App\Models\CmsPage;
use App\Models\Stage;
use App\Models\Task;
use App\Models\Vehicle;
use App\Services\AndroidPushNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebsiteController extends Controller
{
    public function index(): View
    {
        return view('website.index');
    }

    public function listVehicles(): View
    {
        $vehicles = Vehicle::query()
            ->with('websitePhotos')
            ->websiteCatalog()
            ->get();

        return view('website.fleet-index', [
            'vehicles' => $vehicles,
        ]);
    }

    public function storeContact(ContactTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! self::createContactLead($data, $data['source'] ?? 'website_form')) {
            return back()->withErrors(['message' => 'Nao foi possivel criar a tarefa: nenhum estagio inicial configurado.']);
        }

        return back()->with('contact_success', 'Pedido enviado com sucesso. Sera contactado brevemente.');
    }

    public function showCms(CmsPage $page, ?string $slug = null): RedirectResponse|View
    {
        if (! $page->is_active) {
            abort(404);
        }

        $expectedSlug = Str::slug($page->title);

        if ($slug !== $expectedSlug) {
            return redirect()->route('cms.show', [
                'page' => $page->getKey(),
                'slug' => $expectedSlug,
            ]);
        }

        return view('website.cms', [
            'page' => $page,
        ]);
    }

    public function showVehicle(Vehicle $vehicle, ?string $slug = null): RedirectResponse|View
    {
        abort_unless($vehicle->source === 'tvde', 404);

        $vehicle->loadMissing('websitePhotos');

        $expectedSlug = $vehicle->publicSlug();

        if ($slug !== $expectedSlug) {
            return redirect()->route('vehicle.show', [
                'vehicle' => $vehicle,
                'slug' => $expectedSlug,
            ]);
        }

        return view('website.fleet-show', [
            'vehicle' => $vehicle,
        ]);
    }

    public static function createContactLead(array $data, string $source = 'website_form'): ?Task
    {
        $vehicle = null;

        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::query()->find($data['vehicle_id']);
        }

        Log::info('PUSH_DEBUG website_form createContactLead:start', [
            'source' => $source,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'vehicle_id' => $vehicle?->getKey(),
        ]);

        $stage = Stage::query()
            ->where('board_id', 1)
            ->where('is_initial', true)
            ->orderBy('position')
            ->first()
            ?? Stage::query()
                ->where('board_id', 1)
                ->orderBy('position')
                ->first();

        if (! $stage) {
            Log::warning('PUSH_DEBUG website_form createContactLead:no_stage');

            return null;
        }

        $task = DB::transaction(function () use ($data, $stage, $source, $vehicle): Task {
            $nextPosition = (int) Task::query()
                ->where('stage_id', $stage->id)
                ->max('position');

            $title = $vehicle instanceof Vehicle
                ? 'Lead viatura: '.$vehicle->displayName().' - '.$data['name']
                : 'Lead: '.$data['name'];

            $description = $vehicle instanceof Vehicle
                ? "Viatura: {$vehicle->displayName()}\nMatricula: {$vehicle->license_plate}\nEstado: {$vehicle->statusLabel()}\n\n".$data['message']."\nTelefone: ".$data['phone']
                : $data['message']."\nTelefone: ".$data['phone'];

            if (! empty($data['page_url'])) {
                $description .= "\nPagina: ".$data['page_url'];
            }

            return Task::query()->create([
                'board_id' => 1,
                'stage_id' => $stage->id,
                'title' => $title,
                'description' => $description,
                'priority' => 'normal',
                'position' => $nextPosition + 1,
                'meta' => [
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'source' => $source,
                    'contact_name' => $data['name'],
                    'page_url' => $data['page_url'] ?? null,
                    'vehicle_id' => $vehicle?->getKey(),
                    'vehicle_name' => $vehicle?->displayName(),
                    'vehicle_license_plate' => $vehicle?->license_plate,
                ],
            ]);
        });

        $task->load(['assignedTo', 'stage']);

        Log::info('PUSH_DEBUG website_form createContactLead:task_created', [
            'task_id' => $task->id,
            'stage_id' => $task->stage_id,
            'board_id' => $task->board_id,
            'assigned_to_id' => $task->assigned_to_id,
            'meta' => $task->meta,
        ]);

        app(AndroidPushNotificationService::class)->sendNewContactTask($task);

        Log::info('PUSH_DEBUG website_form createContactLead:push_dispatched', [
            'task_id' => $task->id,
        ]);

        return $task;
    }
}
