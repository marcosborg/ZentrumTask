<?php

namespace App\View\Components;

use App\Models\Hero as HeroModel;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Hero extends Component
{
    public mixed $heroes;

    public function __construct()
    {
        $this->heroes = HeroModel::query()
            ->with('media')
            ->latest('id')
            ->get();
    }

    public function render(): View
    {
        return view('components.hero', [
            'heroes' => $this->heroes,
        ]);
    }
}
