<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * Google Analytics 4 Component
 *
 * GA4 is NOT loaded until the user explicitly consents.
 * This is required for GDPR compliance due to EU DPA rulings.
 */
class GoogleAnalytics extends Component
{
    public bool $enabled;
    public bool $cmpEnabled;
    public ?string $measurementId;
    public string $cookieFlags;

    public function __construct()
    {
        $this->cmpEnabled = Cmp::isEnabled();
        $this->enabled = Cmp::isGa4Enabled();
        $this->measurementId = Cmp::getGa4MeasurementId();
        $this->cookieFlags = Cmp::getGa4CookieFlags();
    }

    public function render(): View|string
    {
        if (! $this->cmpEnabled || ! $this->enabled || empty($this->measurementId)) {
            return '';
        }

        return view('cmp::components.google-analytics');
    }
}
