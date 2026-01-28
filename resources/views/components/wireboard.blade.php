{{-- WireBoard Analytics - Legitimate Interest (cookieless by default) --}}
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

        // Load events script
        var eventsScript = document.createElement('script');
        eventsScript.src = '{{ $config['events_js_url'] ?? 'https://static.wireboard.io/events.min.js' }}';
        document.head.appendChild(eventsScript);
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
