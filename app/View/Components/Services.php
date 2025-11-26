<?php

namespace App\View\Components;

use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Services extends Component
{
    public $services;

    public function __construct()
    {
        $this->services = Service::query()
            ->orderBy('id', 'desc')
            ->get();
    }

    public function render(): View
    {
        return view('components.services', [
            'services' => $this->services,
        ]);
    }
}
