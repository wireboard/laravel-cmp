<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * WireBoard Analytics Component
 *
 * WireBoard supports two loading modes:
 * - 'cookieless_first': Load immediately in cookieless mode, upgrade after consent
 * - 'consent_required': Only load after user grants analytics consent (like GA4)
 */
class WireBoard extends Component
{
    public bool $enabled;
    public bool $cmpEnabled;
    public array $config;
    public string $loadingMode;

    /**
     * Whether the deprecated document performance-timing context rides along
     * with every event. Off is the sane choice on a single-page app, where the
     * snapshot describes the first page for the whole session.
     */
    public bool $performanceTiming;

    public function __construct()
    {
        $this->cmpEnabled = Cmp::isEnabled();
        $this->enabled = Cmp::isWireBoardEnabled();
        $this->config = Cmp::getWireBoardConfig();
        $this->loadingMode = $this->config['loading_mode'] ?? 'cookieless_first';
        $this->performanceTiming = (bool) ($this->config['contexts']['performance_timing'] ?? true);
    }

    public function render(): View|string
    {
        if (! $this->cmpEnabled || ! $this->enabled) {
            return '';
        }

        // Validate required config
        if (empty($this->config['pipeline']) || empty($this->config['app_id'])) {
            return '';
        }

        return view('cmp::components.wireboard');
    }
}
