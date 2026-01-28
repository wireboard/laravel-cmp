{{-- Google Analytics 4 - CONSENT REQUIRED --}}
{{-- GA4 is NOT loaded until the user explicitly consents --}}
<script>
    window.__gaId = '{{ $measurementId }}';
    window.__ga4Loaded = false;

    function loadGA4() {
        if (window.__ga4Loaded) return;
        window.__ga4Loaded = true;

        var script = document.createElement('script');
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + window.__gaId;
        script.async = true;
        script.onload = function() {
            // Update consent BEFORE configuring - ensures full tracking mode
            gtag('consent', 'update', { analytics_storage: 'granted' });
            gtag('js', new Date());
            gtag('config', window.__gaId, { 'cookie_flags': '{{ $cookieFlags }}' });
        };
        document.head.appendChild(script);
    }

    // Only load GA4 when user explicitly consents
    window.addEventListener('consent.update', function(e) {
        if (e.detail && e.detail.analytics_storage === 'granted') {
            loadGA4();
        }
    });
</script>
