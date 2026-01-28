<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * All-in-One Scripts Component
 *
 * Renders all CMP consent scripts in the correct order:
 * 1. Consent Mode v2 defaults
 * 2. CMP script (Google Funding Choices or vanilla-cookieconsent)
 * 3. Consent state tracker
 * 4. Google Analytics 4 (consent-gated)
 * 5. WireBoard (legitimate interest, cookieless by default)
 *
 * Usage: <x-cmp::scripts /> or <x-cmp-scripts />
 */
class Scripts extends Component
{
    public bool $enabled;

    public function __construct()
    {
        $this->enabled = Cmp::isEnabled();
    }

    public function render(): View|string
    {
        if (! $this->enabled) {
            return '';
        }

        return view('cmp::components.scripts');
    }
}
