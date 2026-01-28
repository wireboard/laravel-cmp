{{-- Consent Mode v2 Defaults - MUST be first script in <head> --}}
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }

    // Consent Mode v2 defaults (deny everything non-essential)
    gtag('consent', 'default', {
        ad_storage: '{{ $defaults['ad_storage'] ?? 'denied' }}',
        analytics_storage: '{{ $defaults['analytics_storage'] ?? 'denied' }}',
        ad_user_data: '{{ $defaults['ad_user_data'] ?? 'denied' }}',
        ad_personalization: '{{ $defaults['ad_personalization'] ?? 'denied' }}',
        wait_for_update: {{ $defaults['wait_for_update'] ?? 500 }}
    });

    // Track which CMP is being used
    window.__cmpType = '{{ $cmpType }}';
</script>
