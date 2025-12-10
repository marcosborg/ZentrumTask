<?php

namespace App\View\Components;

use App\Models\Hero as HeroModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;

class Hero extends Component
{
    public Collection $heroes;

    public function __construct()
    {
        $this->heroes = Schema::hasTable('heroes')
            ? HeroModel::query()
                ->with('media')
                ->latest('id')
                ->get()
            : (new HeroModel)->newCollection();
    }

    public function render(): View
    {
        return view('components.hero', [
            'heroes' => $this->heroes,
        ]);
    }
}
