<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * WireBoard Analytics Component
 *
 * WireBoard operates under Legitimate Interest and can run in cookieless mode.
 * When user grants consent, it upgrades to use cookies/localStorage.
 */
class WireBoard extends Component
{
    public bool $enabled;
    public bool $cmpEnabled;
    public array $config;

    public function __construct()
    {
        $this->cmpEnabled = Cmp::isEnabled();
        $this->enabled = Cmp::isWireBoardEnabled();
        $this->config = Cmp::getWireBoardConfig();
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
