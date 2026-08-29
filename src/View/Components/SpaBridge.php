<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * Single-page application bridge.
 *
 * Trackers send their page view once, when the document loads. Inertia, Turbo,
 * Livewire and client-side routers then replace the page without ever loading
 * another document, so an entire session is recorded as one page.
 *
 * This component turns every client-side navigation back into a `cmp:pageview`
 * event on `window`, which the WireBoard and GA4 components subscribe to. It
 * emits nothing for the initial load: the trackers already cover that.
 */
class SpaBridge extends Component
{
    public bool $enabled;

    /** @var list<string> */
    public array $events;

    public bool $watchHistory;

    /**
     * Whether the fragment counts as part of the address. Only a hash router
     * (`#/dashboard`) wants this; elsewhere an in-page anchor is not a view.
     */
    public bool $hashRouting;

    /**
     * How long to wait for the document title to settle after a navigation,
     * in milliseconds. Frameworks set the title while the new page mounts, so
     * reporting immediately would carry the previous page's title.
     */
    public int $titleWait;

    public function __construct()
    {
        $spa = Cmp::getSpaConfig();

        $this->enabled = Cmp::isEnabled() && (bool) ($spa['enabled'] ?? true);
        $this->events = array_values(array_filter(
            (array) ($spa['events'] ?? []),
            static fn ($event): bool => is_string($event) && $event !== '',
        ));
        $this->watchHistory = (bool) ($spa['watch_history'] ?? true);
        $this->hashRouting = (bool) ($spa['hash_routing'] ?? false);
        $this->titleWait = max(0, (int) ($spa['title_wait'] ?? 250));
    }

    public function render(): View|string
    {
        if (! $this->enabled) {
            return '';
        }

        return view('cmp::components.spa-bridge');
    }
}
