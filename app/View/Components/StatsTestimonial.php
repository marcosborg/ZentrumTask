<?php

namespace App\View\Components;

use App\Models\Stat;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatsTestimonial extends Component
{
    public $stats;
    public $testimonials;

    public function __construct()
    {
        $this->stats = Stat::query()
            ->orderBy('id')
            ->get();
        $this->testimonials = Testimonial::query()
            ->latest('id')
            ->get();
    }

    public function render(): View
    {
        return view('components.stats-testimonial', [
            'stats' => $this->stats,
            'testimonials' => $this->testimonials,
        ]);
    }
}
