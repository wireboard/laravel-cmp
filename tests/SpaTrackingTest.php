<?php

namespace Wireboard\Cmp\Tests;

/**
 * Inertia, Turbo, Livewire and client-side routers replace the page without a
 * document load. A tracker that sends its page view once at boot therefore
 * records a whole session as one page, which is what these tests guard.
 */
class SpaTrackingTest extends TestCase
{
    /** @return array<string, mixed> */
    private function wireboardOn(): array
    {
        return [
            'wireboard.enabled' => true,
            'wireboard.pipeline' => 'pipeline-0.collector.wireboard.io',
            'wireboard.app_id' => 'finimo',
        ];
    }

    public function test_it_ships_the_spa_bridge_with_the_script_bundle(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        $this->assertStringContainsString('cmp:pageview', $html);
        $this->assertStringContainsString('window.cmpTrackPageView', $html);
    }

    public function test_it_listens_for_every_configured_framework_event(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        foreach (['inertia:navigate', 'turbo:load', 'livewire:navigated'] as $event) {
            $this->assertStringContainsString($event, $html, "missing listener for $event");
        }
    }

    public function test_it_prefers_the_navigation_api_and_falls_back_to_patching(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // Chromium exposes a first-class signal; patching a global browser API
        // is the fallback for browsers that do not, not the first choice.
        $this->assertStringContainsString("navigation.addEventListener('navigatesuccess'", $html);
        $this->assertStringContainsString('popstate', $html);
        $this->assertStringContainsString('pushState', $html);
    }

    public function test_it_collapses_by_address_and_never_by_a_time_window(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // A time window would also swallow a genuine second navigation made
        // moments after the first, which is a lost page view. The repeats a
        // single navigation produces all carry the same address, so comparing
        // addresses collapses them without dropping anything real.
        $this->assertStringContainsString('key === lastKey', $html);
        $this->assertStringNotContainsString('Date.now()', $html);
    }

    public function test_it_does_not_count_an_in_page_anchor_as_a_navigation(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // Clicking #pricing on a classic Blade page is a jump within the page.
        // Chromium's Navigation API reports it as a navigation while Firefox
        // and Safari do not, so counting it would split the numbers by
        // browser. The fragment is dropped before addresses are compared.
        $this->assertStringContainsString('var hashRouting = false;', $html);
        $this->assertStringContainsString('function navKey(href)', $html);
    }

    public function test_a_hash_router_can_opt_into_counting_the_fragment(): void
    {
        $html = $this->renderScripts(array_merge($this->wireboardOn(), ['spa.hash_routing' => true]));

        // Where #/dashboard really is the address, the fragment has to count.
        $this->assertStringContainsString('var hashRouting = true;', $html);
    }

    public function test_it_keeps_the_fragment_out_of_the_address_when_config_is_missing(): void
    {
        // An app upgrading from 1.5.x has a published config with no `spa`
        // block, and a cached config drops it entirely. Both land on the
        // component's own fallback, which must be the safe one.
        $html = $this->renderScripts(array_merge($this->wireboardOn(), ['spa' => []]));

        $this->assertStringContainsString('var hashRouting = false;', $html);
        $this->assertStringContainsString('window.cmpTrackPageView', $html);
    }

    public function test_it_waits_for_the_title_before_reporting(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // Frameworks set document.title while the new page mounts, so a report
        // sent immediately would carry the previous page's title.
        $this->assertStringContainsString('MutationObserver', $html);
        $this->assertStringContainsString('whenTitleSettles', $html);
    }

    public function test_the_history_watch_can_be_turned_off(): void
    {
        $html = $this->renderScripts(array_merge($this->wireboardOn(), ['spa.watch_history' => false]));

        $this->assertStringContainsString('inertia:navigate', $html);
        $this->assertStringNotContainsString('popstate', $html);
    }

    public function test_the_bridge_can_be_turned_off_entirely(): void
    {
        $html = $this->renderScripts(array_merge($this->wireboardOn(), ['spa.enabled' => false]));

        $this->assertStringNotContainsString('window.cmpTrackPageView', $html);
    }

    public function test_wireboard_sends_a_page_view_on_client_side_navigation(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // Not just at boot: a listener has to re-send it.
        $this->assertStringContainsString("addEventListener('cmp:pageview'", $html);
        $this->assertStringContainsString('setReferrerUrl', $html);
    }

    public function test_wireboard_page_views_survive_a_fault_in_the_vendor_sdk(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // The SDK is third-party code loaded from a CDN; a throw inside it
        // must not take the host page down.
        $this->assertMatchesRegularExpression('/try \{.*trackPageView.*catch/s', $html);
    }

    public function test_google_analytics_does_not_double_count_by_default(): void
    {
        $html = $this->renderScripts([
            'google_analytics.enabled' => true,
            'google_analytics.measurement_id' => 'G-TEST123',
        ]);

        // GA4 Enhanced Measurement already reports history navigations and is
        // on by default, so sending our own on top of it would double-count.
        $this->assertStringNotContainsString("gtag('event', 'page_view'", $html);
    }

    public function test_google_analytics_can_opt_in_to_sending_page_views(): void
    {
        $html = $this->renderScripts([
            'google_analytics.enabled' => true,
            'google_analytics.measurement_id' => 'G-TEST123',
            'google_analytics.spa_page_views' => true,
        ]);

        $this->assertStringContainsString("addEventListener('cmp:pageview'", $html);
        $this->assertStringContainsString("gtag('event', 'page_view'", $html);
    }

    public function test_the_ga4_opt_in_still_honours_the_master_spa_switch(): void
    {
        $html = $this->renderScripts([
            'google_analytics.enabled' => true,
            'google_analytics.measurement_id' => 'G-TEST123',
            'google_analytics.spa_page_views' => true,
            'spa.enabled' => false,
        ]);

        $this->assertStringNotContainsString("gtag('event', 'page_view'", $html);
    }
}
