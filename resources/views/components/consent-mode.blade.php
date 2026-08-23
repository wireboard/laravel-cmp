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
        functionality_storage: '{{ $defaults['functionality_storage'] ?? 'denied' }}',
        personalization_storage: '{{ $defaults['personalization_storage'] ?? 'denied' }}',
        security_storage: '{{ $defaults['security_storage'] ?? 'granted' }}',
        wait_for_update: {{ $defaults['wait_for_update'] ?? 500 }}
    });

    @if($adsDataRedaction)
    // Redact ads click identifiers while ad_storage is denied
    gtag('set', 'ads_data_redaction', true);
    @endif
    @if($urlPassthrough)
    // Pass ad click information through URLs when cookies are denied
    gtag('set', 'url_passthrough', true);
    @endif

    // Track which CMP is being used
    window.__cmpType = '{{ $cmpType }}';
</script>
