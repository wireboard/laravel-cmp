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

    public function test_only_a_changed_title_ends_the_wait(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // Anything else touching the head (a preload link for a lazy chunk, a
        // script tag, meta tags dropped before the title arrives) used to end
        // the wait with the previous page's title still in place. Only a title
        // that differs from the one seen at navigation may end it; the timeout
        // covers a page that keeps the same title.
        $this->assertStringContainsString('document.title !== before', $html);
        $this->assertStringNotContainsString("nodeName === 'HEAD'", $html);
        $this->assertStringContainsString('var titleBefore = document.title;', $html);
    }

    public function test_a_report_names_the_page_it_was_announced_for(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // The wait can outlast the page: read the address at dispatch and a
        // report that goes out after the next navigation names the wrong page.
        $this->assertStringContainsString('dispatch(url, previous, titleBefore)', $html);
        $this->assertStringContainsString('url: url,', $html);
        $this->assertStringNotContainsString('url: window.location.href', $html);
    }

    public function test_the_next_navigation_flushes_a_report_still_waiting(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // A page that keeps the previous title waits the full title_wait. A
        // navigation inside that window must send the waiting report first,
        // or the wait ends on the newer page's title and that page is
        // reported twice while the one in between is never reported.
        $this->assertStringContainsString('settle = whenTitleSettles(', $html);
        $this->assertMatchesRegularExpression('/if \(settle\) \{\s*settle\(\);/', $html);
        $this->assertStringContainsString('return finish;', $html);
    }

    public function test_the_browser_suite_covers_the_bridge_end_to_end(): void
    {
        // String checks above pin the mechanism; the real guarantee is the
        // Chromium run in tests/browser, which drives the bridge through
        // Inertia's navigation order on both signal paths.
        $this->assertFileExists(__DIR__ . '/browser/spa-bridge.test.js');
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

        // Not just at boot: a listener has to re-send it, under the address,
        // referrer and title the bridge reported. The SDK reading the
        // location itself names the wrong page for a view sent late.
        $this->assertStringContainsString("addEventListener('cmp:pageview'", $html);
        $this->assertStringContainsString("wireboard('setCustomUrl', view.url)", $html);
        $this->assertStringContainsString("wireboard('setReferrerUrl', view.referrer)", $html);
        $this->assertStringContainsString("wireboard('trackPageView', title", $html);
    }

    public function test_wireboard_keeps_page_views_that_arrive_before_the_tracker(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        // Cookieless-first is the default, and its tracker only comes up on
        // a consent interaction or after the initialization timeout. Views
        // from those first seconds must be kept and sent after the landing
        // page, under its own address, or the entry page is misrecorded.
        $this->assertStringContainsString('var landingUrl = window.location.href;', $html);
        $this->assertStringContainsString('early.push(view);', $html);
        $this->assertStringContainsString('url: landingUrl, title: early[0].previousTitle', $html);
        $this->assertStringContainsString('previousTitle: previousTitle', $html);
    }

    public function test_wireboard_tracks_nothing_before_consent_when_consent_is_required(): void
    {
        $html = $this->renderScripts(array_merge($this->wireboardOn(), ['wireboard.loading_mode' => 'consent_required']));

        $this->assertStringContainsString('if (!trackerReady) return;', $html);
        $this->assertStringNotContainsString('early.push', $html);
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
