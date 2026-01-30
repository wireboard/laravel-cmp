<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * Consent State Tracker Component
 *
 * Initializes global consent state and exposes the updateConsent function
 * for the Custom CMP to use. Google Funding Choices callbacks are handled
 * in CmpScript component to avoid race conditions.
 */
class ConsentTracker extends Component
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

        return view('cmp::components.consent-tracker');
    }
}
