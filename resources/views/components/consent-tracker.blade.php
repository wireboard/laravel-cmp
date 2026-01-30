{{-- Consent State Tracker - bridges Custom CMP to unified consent.update event --}}
{{--
    This component initializes the global consent state and exposes the
    updateConsent function for the Custom CMP to use.

    Note: Google Funding Choices callback handling is done in cmp-script.blade.php
    to avoid race conditions (callback must be registered before Google FC loads).
--}}
<script>
(function(){
    // Global consent state (may already be initialized by cmp-script for Google CMP)
    window.__consentState = window.__consentState || {
        ad_storage: 'denied',
        analytics_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied'
    };

    // Function to update consent and notify listeners (used by Custom CMP)
    function updateConsent(consentValues) {
        if (consentValues.ad_storage === 'GRANTED' || consentValues.ad_storage === 'granted') {
            window.__consentState.ad_storage = 'granted';
        }
        if (consentValues.analytics_storage === 'GRANTED' || consentValues.analytics_storage === 'granted') {
            window.__consentState.analytics_storage = 'granted';
        }
        if (consentValues.ad_user_data === 'GRANTED' || consentValues.ad_user_data === 'granted') {
            window.__consentState.ad_user_data = 'granted';
        }
        if (consentValues.ad_personalization === 'GRANTED' || consentValues.ad_personalization === 'granted') {
            window.__consentState.ad_personalization = 'granted';
        }

        // Update Google's consent mode
        if (typeof gtag === 'function') {
            gtag('consent', 'update', {
                ad_storage: window.__consentState.ad_storage,
                analytics_storage: window.__consentState.analytics_storage,
                ad_user_data: window.__consentState.ad_user_data,
                ad_personalization: window.__consentState.ad_personalization
            });
        }

        // Fire custom event for GA/WireBoard
        window.dispatchEvent(new CustomEvent('consent.update', {
            detail: window.__consentState
        }));
    }

    // Expose updateConsent for Custom CMP
    window.__cmpUpdateConsent = updateConsent;
})();
</script>
