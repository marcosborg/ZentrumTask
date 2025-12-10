<?php

namespace App\View\Components;

use App\Models\Fleet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;

class FleetFaqs extends Component
{
    public Collection $fleets;

    public function __construct()
    {
        $this->fleets = Schema::hasTable('fleets')
            ? Fleet::query()
                ->latest('id')
                ->get()
            : (new Fleet)->newCollection();
    }

    public function render(): View
    {
        return view('components.fleet-faqs', [
            'fleets' => $this->fleets,
        ]);
    }
}
