<?php

namespace App\View\Components;

use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class FleetFaqs extends Component
{
    public Collection $vehicles;

    public function __construct()
    {
        $this->vehicles = (new Vehicle)->newCollection();
    }

    public function render(): View
    {
        return view('components.fleet-faqs', [
            'vehicles' => $this->vehicles,
        ]);
    }
}
