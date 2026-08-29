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

    /**
     * Whether to send our own page view on client-side navigation.
     *
     * Off by default: GA4's Enhanced Measurement already reports "page changes
     * based on browser history events", and that option ships enabled on every
     * web data stream, so sending one as well double-counts. Turn this on only
     * after switching that option off in the GA4 UI.
     */
    public bool $spaPageViews;

    public function __construct()
    {
        $this->cmpEnabled = Cmp::isEnabled();
        $this->enabled = Cmp::isGa4Enabled();
        $this->measurementId = Cmp::getGa4MeasurementId();
        $this->cookieFlags = Cmp::getGa4CookieFlags();
        $this->spaPageViews = Cmp::isSpaTrackingEnabled()
            && (bool) Cmp::get('google_analytics.spa_page_views', false);
    }

    public function render(): View|string
    {
        if (! $this->cmpEnabled || ! $this->enabled || empty($this->measurementId)) {
            return '';
        }

        return view('cmp::components.google-analytics');
    }
}
