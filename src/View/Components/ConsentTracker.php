<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * Consent State Tracker Component
 *
 * Bridges the CMP APIs (Google Funding Choices or vanilla-cookieconsent)
 * to a unified 'consent.update' event that other scripts can listen to.
 */
class ConsentTracker extends Component
{
    public bool $enabled;
    public string $cmpType;

    public function __construct()
    {
        $this->enabled = Cmp::isEnabled();
        $this->cmpType = Cmp::getCmpType();
    }

    public function render(): View|string
    {
        if (! $this->enabled) {
            return '';
        }

        return view('cmp::components.consent-tracker');
    }
}
