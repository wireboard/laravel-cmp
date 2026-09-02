{{-- WireBoard Analytics --}}
@php
    $sdkUrl = $config['js_url'] ?? 'https://static.wireboard.io/wireboard.js';
    $eventsUrl = $config['events_js_url'] ?? 'https://static.wireboard.io/events.min.js';
    $publisher = $config['publisher'] ?? null;
@endphp
@if($loadingMode === 'consent_required')
{{-- Consent Required Mode: Only load after user grants analytics consent (like GA4) --}}
<script>
(function() {
    var wireboardLoaded = false;
    var trackerReady = false;

    function loadWireBoard() {
        if (wireboardLoaded) return;
        wireboardLoaded = true;

        // Load WireBoard SDK
        ;(function(w,i,r,e,b,oar,d){
            if(!w[b]){
                w.WireBoardNamespace=w.WireBoardNamespace||[];
                w.WireBoardNamespace.push(b);
                w[b]=function(){(w[b].q=w[b].q||[]).push(arguments)};
                w[b].q=w[b].q||[];
                oar=i.createElement(r);
                d=i.getElementsByTagName(r)[0];
                oar.async=1;
                oar.src=e;
                oar.onload = initTracker;
                d.parentNode.insertBefore(oar,d);
            } else {
                // Another snippet already loaded the SDK: still set up our
                // tracker, otherwise nothing is ever sent.
                initTracker();
            }
        }(window,document,"script",@json($sdkUrl),"wireboard"));
    }

    function initTracker() {
        if (trackerReady) return;
        trackerReady = true;

        wireboard('newTracker', 'wb', @json($config['pipeline']), {
            appId: @json($config['app_id']),
            forceSecureTracker: true,
            useCookies: true,
            useLocalStorage: true,
            contexts: {
                performanceTiming: @json($performanceTiming)
            }
        });
        wireboard('enableActivityTracking', 5, 10);

        trackPageView();

        @if(!empty($config['load_events_script']))
        // Load events script for automatic event tracking
        var eventsScript = document.createElement('script');
        eventsScript.src = @json($eventsUrl);
        document.head.appendChild(eventsScript);
        @endif
    }

    /**
     * Send one page view. `view` names the address, referrer and title of the
     * page it describes, so a view the bridge sends late (flushed by the next
     * navigation) still reports its own page and not the one the visitor is
     * on by now. Wrapped: a fault inside the vendor SDK must never take the
     * host page down with it.
     */
    function trackPageView(view) {
        try {
            if (view && view.url) {
                wireboard('setCustomUrl', view.url);
            }
            if (view && view.referrer) {
                wireboard('setReferrerUrl', view.referrer);
            }
            var title = view && view.title ? view.title : null;
            @if(!empty($publisher))
            wireboard('trackPageView', title, [{
                schema: 'wb:io.wireboard/publisher',
                data: { publisher: @json($publisher) }
            }]);
            @else
            wireboard('trackPageView', title);
            @endif
        } catch (error) {
            if (window.console && console.debug) {
                console.debug('[cmp] WireBoard page view skipped', error);
            }
        }
    }

    // Client-side navigation, announced by the SPA bridge component. Views
    // from before consent are not kept: nothing is tracked until it is given.
    window.addEventListener('cmp:pageview', function (event) {
        if (!trackerReady) return;
        trackPageView(event.detail || {});
    });

    // Listen for consent - only load when analytics consent is granted
    window.addEventListener('consent.update', function(e) {
        if (e.detail && e.detail.analytics_storage === 'granted') {
            loadWireBoard();
        }
    });

    // Check if consent was already granted (returning user)
    if (window.__consentState && window.__consentState.analytics_storage === 'granted') {
        loadWireBoard();
    }
})();
</script>
@else
{{-- Cookieless First Mode (default): Load immediately in cookieless mode, upgrade after consent --}}
<script>
(function() {
    var wireboardInitialized = false;

    // Tracking in this mode starts at the landing page, but the tracker only
    // comes up on a consent interaction or after the initialization timeout.
    // Page views announced before that are kept, and sent once it is up:
    // the landing page first, under its own address, then the rest in order.
    // Without this a visitor who clicks through in the first seconds loses
    // the landing page, and the entry is recorded on whatever page they had
    // reached by then.
    var landingUrl = window.location.href;
    var early = [];

    // Load WireBoard SDK immediately (just the library, not the tracker)
    ;(function(w,i,r,e,b,oar,d){
        if(!w[b]){
            w.WireBoardNamespace=w.WireBoardNamespace||[];
            w.WireBoardNamespace.push(b);
            w[b]=function(){(w[b].q=w[b].q||[]).push(arguments)};
            w[b].q=w[b].q||[];
            oar=i.createElement(r);
            d=i.getElementsByTagName(r)[0];
            oar.async=1;
            oar.src=e;
            d.parentNode.insertBefore(oar,d);
        }
    }(window,document,"script",@json($sdkUrl),"wireboard"));

    // Initialize WireBoard with appropriate consent settings
    function initWireBoard(hasConsent) {
        if (wireboardInitialized) {
            // Already initialized - upgrade storage if consent granted mid-session
            if (hasConsent) {
                wireboard('upgradeStorage', {
                    useCookies: true,
                    useLocalStorage: true
                });
            }
            return;
        }
        wireboardInitialized = true;

        wireboard('newTracker', 'wb', @json($config['pipeline']), {
            appId: @json($config['app_id']),
            forceSecureTracker: true,
            useCookies: hasConsent,
            useLocalStorage: hasConsent,
            contexts: {
                performanceTiming: @json($performanceTiming)
            }
        });
        wireboard('enableActivityTracking', 5, 10);

        // With nothing queued the landing page is the current one and the
        // SDK reads its address and title itself. Otherwise both are named,
        // since the visitor has moved on; the bridge saw the title the
        // landing page had when they left it.
        trackPageView(early.length ? { url: landingUrl, title: early[0].previousTitle } : null);

        for (var i = 0; i < early.length; i++) {
            trackPageView(early[i]);
        }
        early = [];

        @if(!empty($config['load_events_script']))
        // Load events script for automatic event tracking
        var eventsScript = document.createElement('script');
        eventsScript.src = @json($eventsUrl);
        document.head.appendChild(eventsScript);
        @endif
    }

    /**
     * Send one page view. `view` names the address, referrer and title of the
     * page it describes, so a view sent late still reports its own page and
     * not the one the visitor is on by now. Wrapped: a fault inside the
     * vendor SDK must never take the host page down with it.
     */
    function trackPageView(view) {
        try {
            if (view && view.url) {
                wireboard('setCustomUrl', view.url);
            }
            if (view && view.referrer) {
                wireboard('setReferrerUrl', view.referrer);
            }
            var title = view && view.title ? view.title : null;
            @if(!empty($publisher))
            wireboard('trackPageView', title, [{
                schema: 'wb:io.wireboard/publisher',
                data: { publisher: @json($publisher) }
            }]);
            @else
            wireboard('trackPageView', title);
            @endif
        } catch (error) {
            if (window.console && console.debug) {
                console.debug('[cmp] WireBoard page view skipped', error);
            }
        }
    }

    // Client-side navigation, announced by the SPA bridge component.
    window.addEventListener('cmp:pageview', function (event) {
        var view = event.detail || {};

        if (!wireboardInitialized) {
            early.push(view);
            return;
        }

        trackPageView(view);
    });

    // Listen for consent updates (user interacts with CMP)
    window.addEventListener('consent.update', function(e) {
        var hasConsent = !!(e.detail && e.detail.analytics_storage === 'granted');
        initWireBoard(hasConsent);
    });

    // Fallback: Initialize after timeout if no consent interaction
    // Handles: returning users with stored consent, or users who ignore CMP
    setTimeout(function() {
        if (!wireboardInitialized) {
            var hasConsent = !!(window.__consentState && window.__consentState.analytics_storage === 'granted');
            initWireBoard(hasConsent);
        }
    }, {{ (int) ($config['initialization_timeout'] ?? 2000) }});
})();
</script>
@endif
