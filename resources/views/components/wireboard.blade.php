{{-- WireBoard Analytics --}}
@if($loadingMode === 'consent_required')
{{-- Consent Required Mode: Only load after user grants analytics consent (like GA4) --}}
<script>
(function() {
    var wireboardLoaded = false;

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
            }
        }(window,document,"script","{{ $config['js_url'] ?? 'https://static.wireboard.io/wireboard.js' }}","wireboard"));
    }

    function initTracker() {
        wireboard('newTracker', 'wb', '{{ $config['pipeline'] }}', {
            appId: '{{ $config['app_id'] }}',
            forceSecureTracker: true,
            useCookies: true,
            useLocalStorage: true,
            contexts: {
                performanceTiming: true
            }
        });
        wireboard('enableActivityTracking', 5, 10);

        @if(!empty($config['publisher']))
        var customContext = [{
            schema: 'wb:io.wireboard/publisher',
            data: { publisher: '{{ $config['publisher'] }}' }
        }];
        wireboard('trackPageView', null, customContext);
        @else
        wireboard('trackPageView');
        @endif

        @if(!empty($config['load_events_script']))
        // Load events script for automatic event tracking
        var eventsScript = document.createElement('script');
        eventsScript.src = '{{ $config['events_js_url'] ?? 'https://static.wireboard.io/events.min.js' }}';
        document.head.appendChild(eventsScript);
        @endif
    }

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
    }(window,document,"script","{{ $config['js_url'] ?? 'https://static.wireboard.io/wireboard.js' }}","wireboard"));

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

        wireboard('newTracker', 'wb', '{{ $config['pipeline'] }}', {
            appId: '{{ $config['app_id'] }}',
            forceSecureTracker: true,
            useCookies: hasConsent,
            useLocalStorage: hasConsent,
            contexts: {
                performanceTiming: true
            }
        });
        wireboard('enableActivityTracking', 5, 10);

        @if(!empty($config['publisher']))
        var customContext = [{
            schema: 'wb:io.wireboard/publisher',
            data: { publisher: '{{ $config['publisher'] }}' }
        }];
        wireboard('trackPageView', null, customContext);
        @else
        wireboard('trackPageView');
        @endif

        @if(!empty($config['load_events_script']))
        // Load events script for automatic event tracking
        var eventsScript = document.createElement('script');
        eventsScript.src = '{{ $config['events_js_url'] ?? 'https://static.wireboard.io/events.min.js' }}';
        document.head.appendChild(eventsScript);
        @endif
    }

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
    }, {{ $config['initialization_timeout'] ?? 2000 }});
})();
</script>
@endif
