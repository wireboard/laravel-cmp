<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * CMP Script Component
 *
 * Loads the appropriate Consent Management Platform:
 * - Google Funding Choices (when AdSense is enabled)
 * - vanilla-cookieconsent (when AdSense is disabled)
 */
class CmpScript extends Component
{
    public bool $enabled;
    public bool $useGoogleCmp;
    public ?string $adsensePubId;
    public array $customCmpConfig;
    public string $themeCssVariables;

    public function __construct()
    {
        $this->enabled = Cmp::isEnabled();
        $this->useGoogleCmp = Cmp::isAdSenseEnabled();
        $this->adsensePubId = Cmp::getAdSensePubId();
        $this->customCmpConfig = Cmp::getCustomCmpConfig();
        $this->themeCssVariables = Cmp::getThemeCssVariables();
    }

    public function render(): View|string
    {
        if (! $this->enabled) {
            return '';
        }

        return view('cmp::components.cmp-script');
    }
}
