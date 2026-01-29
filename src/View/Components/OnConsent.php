<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * On-Consent Component
 *
 * Wraps content that should only load after user grants consent for a specific category.
 * Useful for loading third-party analytics, marketing scripts, or embeds.
 *
 * Usage:
 *   <x-cmp::on-consent category="analytics">
 *       <script src="https://example.com/analytics.js"></script>
 *   </x-cmp::on-consent>
 *
 *   <x-cmp::on-consent category="marketing">
 *       <script src="https://example.com/ads.js"></script>
 *   </x-cmp::on-consent>
 */
class OnConsent extends Component
{
    public bool $enabled;
    public string $category;
    public string $consentType;
    public string $uniqueId;

    /**
     * @param string $category The consent category: 'analytics' or 'marketing'
     */
    public function __construct(string $category = 'analytics')
    {
        $this->enabled = Cmp::isEnabled();
        $this->category = $category;
        $this->uniqueId = 'cmp-' . uniqid();

        // Map category to consent type
        $this->consentType = match ($category) {
            'marketing', 'ads' => 'ad_storage',
            default => 'analytics_storage',
        };
    }

    public function render(): View|string
    {
        if (! $this->enabled) {
            // CMP disabled - render content immediately
            return '{{ $slot }}';
        }

        return view('cmp::components.on-consent');
    }
}
