<?php

namespace App\View\Components;

use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;

class Services extends Component
{
    public Collection $services;

    public function __construct()
    {
        $this->services = Schema::hasTable('services')
            ? Service::query()
                ->orderBy('id', 'desc')
                ->get()
            : (new Service)->newCollection();
    }

    public function render(): View
    {
        return view('components.services', [
            'services' => $this->services,
        ]);
    }
}
