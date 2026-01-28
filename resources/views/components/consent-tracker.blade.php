{{-- Consent State Tracker - bridges CMP APIs to unified consent.update event --}}
<script>
(function(){
    // Global consent state
    window.__consentState = {
        ad_storage: 'denied',
        analytics_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied'
    };

    // Function to update consent and notify listeners
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

    @if($cmpType === 'google')
    // Google Funding Choices callbacks
    window.googlefc = window.googlefc || {};
    window.googlefc.callbackQueue = window.googlefc.callbackQueue || [];

    // Register callback for when consent data is ready
    window.googlefc.callbackQueue.push({
        CONSENT_DATA_READY: function() {
            if (typeof window.googlefc.getGoogleConsentModeValues === 'function') {
                var consentValues = window.googlefc.getGoogleConsentModeValues();
                updateConsent(consentValues);
            }
        }
    });

    // Fallback: TCF API listener
    setTimeout(function() {
        if (typeof window.__tcfapi === 'function') {
            window.__tcfapi('addEventListener', 2, function(tcData, success) {
                if (success && tcData.purpose) {
                    var hasConsent = tcData.purpose.consents && (
                        tcData.purpose.consents[1] ||
                        tcData.purpose.consents[7]
                    );

                    if (hasConsent && (tcData.eventStatus === 'useractioncomplete' || tcData.eventStatus === 'tcloaded')) {
                        updateConsent({
                            ad_storage: 'GRANTED',
                            analytics_storage: 'GRANTED',
                            ad_user_data: 'GRANTED',
                            ad_personalization: 'GRANTED'
                        });
                    }
                }
            });
        }
    }, 500);
    @endif

    // Expose updateConsent for custom CMP
    window.__cmpUpdateConsent = updateConsent;
})();
</script>

@if($cmpType === 'custom')
{{-- Custom CMP initialization --}}
<script src="{{ config('cmp.custom_cmp.cmp_js_path', '/vendor/cmp/js/consent-cmp.min.js') }}"></script>
@endif
