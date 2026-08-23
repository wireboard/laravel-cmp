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
        ad_personalization: 'denied',
        functionality_storage: 'denied',
        personalization_storage: 'denied',
        security_storage: 'granted'
    };

    var CONSENT_KEYS = [
        'ad_storage',
        'analytics_storage',
        'ad_user_data',
        'ad_personalization',
        'functionality_storage',
        'personalization_storage',
        'security_storage'
    ];

    // Function to update consent and notify listeners (used by Custom CMP).
    // Mirrors granted AND denied so revoking consent downgrades the state.
    function updateConsent(consentValues) {
        CONSENT_KEYS.forEach(function (key) {
            var value = consentValues[key];
            if (value === 'GRANTED' || value === 'granted') {
                window.__consentState[key] = 'granted';
            } else if (value === 'DENIED' || value === 'denied') {
                window.__consentState[key] = 'denied';
            }
        });

        // Update Google's consent mode
        if (typeof gtag === 'function') {
            gtag('consent', 'update', {
                ad_storage: window.__consentState.ad_storage,
                analytics_storage: window.__consentState.analytics_storage,
                ad_user_data: window.__consentState.ad_user_data,
                ad_personalization: window.__consentState.ad_personalization,
                functionality_storage: window.__consentState.functionality_storage,
                personalization_storage: window.__consentState.personalization_storage,
                security_storage: window.__consentState.security_storage
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
