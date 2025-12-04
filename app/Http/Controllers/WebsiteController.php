<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactTaskRequest;
use App\Models\CmsPage;
use App\Models\Stage;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebsiteController extends Controller
{
    public function index()
    {
        return view('website.index');
    }

    public function storeContact(ContactTaskRequest $request)
    {
        $data = $request->validated();

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
            return back()->withErrors(['message' => 'NÇœo foi possÇðvel criar a tarefa: nenhum estÇ­gio inicial configurado.']);
        }

        DB::transaction(function () use ($data, $stage): void {
            $nextPosition = (int) Task::query()
                ->where('stage_id', $stage->id)
                ->max('position');

            Task::query()->create([
                'board_id' => 1,
                'stage_id' => $stage->id,
                'title' => 'Lead: '.$data['name'],
                'description' => $data['message']."\nTelefone: ".$data['phone'],
                'priority' => 'normal',
                'position' => $nextPosition + 1,
                'meta' => [
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'source' => 'website_form',
                ],
            ]);
        });

        return back()->with('contact_success', 'Obrigado! CriÇ­mos uma tarefa no Kanban.');
    }

    public function showCms(CmsPage $page, ?string $slug = null)
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
}
