<?php

namespace App\View\Components;

use App\Models\Fleet;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FleetFaqs extends Component
{
    public $fleets;

    public function __construct()
    {
        $this->fleets = Fleet::query()
            ->latest('id')
            ->get();
    }

    public function render(): View
    {
        return view('components.fleet-faqs', [
            'fleets' => $this->fleets,
        ]);
    }
}
