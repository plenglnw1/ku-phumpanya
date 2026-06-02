<?php

declare(strict_types=1);

namespace App\View\Components\Layouts;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Phumpanya extends Component
{
    public function __construct(
        public $recentSearches = null,
        public string $activeNav = 'search',
        public ?string $title = null,
    ) {}

    public function render(): View|Closure|string
    {
        return view('layouts.phumpanya', [
            'recentSearches' => $this->recentSearches ?? collect(),
            'activeNav' => $this->activeNav,
            'title' => $this->title,
        ]);
    }
}
