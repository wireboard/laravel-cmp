<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * Consent Mode v2 Defaults Component
 *
 * MUST be the first script in <head>, before any other tracking scripts.
 * Sets all consent types to 'denied' by default.
 */
class ConsentMode extends Component
{
    public bool $enabled;
    public string $cmpType;
    public array $defaults;

    public function __construct()
    {
        $this->enabled = Cmp::isEnabled();
        $this->cmpType = Cmp::getCmpType();
        $this->defaults = Cmp::getConsentDefaults();
    }

    public function render(): View|string
    {
        if (! $this->enabled) {
            return '';
        }

        return view('cmp::components.consent-mode');
    }
}
