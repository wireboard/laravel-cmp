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
     * Send one page view. Wrapped: a fault inside the vendor SDK must never
     * take the host page down with it.
     */
    function trackPageView(referrer) {
        try {
            if (referrer) {
                wireboard('setReferrerUrl', referrer);
            }
            @if(!empty($publisher))
            wireboard('trackPageView', null, [{
                schema: 'wb:io.wireboard/publisher',
                data: { publisher: @json($publisher) }
            }]);
            @else
            wireboard('trackPageView');
            @endif
        } catch (error) {
            if (window.console && console.debug) {
                console.debug('[cmp] WireBoard page view skipped', error);
            }
        }
    }

    // Client-side navigation, announced by the SPA bridge component.
    window.addEventListener('cmp:pageview', function (event) {
        if (!trackerReady) return;
        trackPageView(event.detail && event.detail.referrer);
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

        trackPageView();

        @if(!empty($config['load_events_script']))
        // Load events script for automatic event tracking
        var eventsScript = document.createElement('script');
        eventsScript.src = @json($eventsUrl);
        document.head.appendChild(eventsScript);
        @endif
    }

    /**
     * Send one page view. Wrapped: a fault inside the vendor SDK must never
     * take the host page down with it.
     */
    function trackPageView(referrer) {
        try {
            if (referrer) {
                wireboard('setReferrerUrl', referrer);
            }
            @if(!empty($publisher))
            wireboard('trackPageView', null, [{
                schema: 'wb:io.wireboard/publisher',
                data: { publisher: @json($publisher) }
            }]);
            @else
            wireboard('trackPageView');
            @endif
        } catch (error) {
            if (window.console && console.debug) {
                console.debug('[cmp] WireBoard page view skipped', error);
            }
        }
    }

    // Client-side navigation, announced by the SPA bridge component.
    window.addEventListener('cmp:pageview', function (event) {
        if (!wireboardInitialized) return;
        trackPageView(event.detail && event.detail.referrer);
    });

    // Listen for consent updates (user interacts with CMP)
    window.addEventListener('consent.update', function(e) {
        var hasConsent = (e.detail && e.detail.analytics_storage === 'granted');
        initWireBoard(hasConsent);
    });

    // Fallback: Initialize after timeout if no consent interaction
    // Handles: returning users with stored consent, or users who ignore CMP
    setTimeout(function() {
        if (!wireboardInitialized) {
            var hasConsent = (window.__consentState && window.__consentState.analytics_storage === 'granted');
            initWireBoard(hasConsent);
        }
    }, {{ (int) ($config['initialization_timeout'] ?? 2000) }});
})();
</script>
@endif
