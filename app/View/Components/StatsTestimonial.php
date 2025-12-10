<?php

namespace App\View\Components;

use App\Models\Stat;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;

class StatsTestimonial extends Component
{
    public Collection $stats;

    public Collection $testimonials;

    public function __construct()
    {
        $this->stats = Schema::hasTable('stats')
            ? Stat::query()
                ->orderBy('id')
                ->get()
            : (new Stat)->newCollection();

        $this->testimonials = Schema::hasTable('testimonials')
            ? Testimonial::query()
                ->latest('id')
                ->get()
            : (new Testimonial)->newCollection();
    }

    public function render(): View
    {
        return view('components.stats-testimonial', [
            'stats' => $this->stats,
            'testimonials' => $this->testimonials,
        ]);
    }
}
