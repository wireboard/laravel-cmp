{{-- Google Analytics 4 - CONSENT REQUIRED --}}
{{-- GA4 is NOT loaded until the user explicitly consents --}}
<script>
    window.__gaId = @json($measurementId);
    window.__ga4Loaded = false;

    function loadGA4() {
        if (window.__ga4Loaded) return;
        window.__ga4Loaded = true;

        var script = document.createElement('script');
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(window.__gaId);
        script.async = true;
        script.onload = function() {
            // Update consent BEFORE configuring - ensures full tracking mode
            gtag('consent', 'update', { analytics_storage: 'granted' });
            gtag('js', new Date());
            gtag('config', window.__gaId, { 'cookie_flags': @json($cookieFlags) });
        };
        document.head.appendChild(script);
    }

    // Only load GA4 when user explicitly consents
    window.addEventListener('consent.update', function(e) {
        if (e.detail && e.detail.analytics_storage === 'granted') {
            loadGA4();
        }
    });

@if($spaPageViews)
    {{-- Opt-in only. GA4's Enhanced Measurement already reports "page changes
         based on browser history events", and that option is ON by default on
         every web data stream, so sending our own page view on top of it
         double-counts. Enable this ONLY after switching that option off in the
         GA4 UI (Admin > Data streams > Enhanced measurement). --}}
    window.addEventListener('cmp:pageview', function (event) {
        if (!window.__ga4Loaded || typeof gtag !== 'function') return;

        var detail = event.detail || {};

        gtag('event', 'page_view', {
            page_location: detail.url || window.location.href,
            page_title: detail.title || document.title,
            page_referrer: detail.referrer
        });
    });
@endif
</script>
