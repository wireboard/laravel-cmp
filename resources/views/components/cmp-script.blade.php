{{-- CMP Script - loads on ALL pages --}}
{{--
    Custom CMP assets are ALWAYS loaded (even when using Google Funding Choices)
    because Google FC doesn't show banners in all regions (e.g., Brazil/LGPD).
    The Custom CMP serves as a fallback when Google returns status 0, 3, or 4.
--}}

{{-- Custom CMP CSS - always loaded for fallback support --}}
<link rel="stylesheet" href="{{ $customCmpConfig['css_path'] ?? '/vendor/cmp/css/cookieconsent.min.css' }}">
<link rel="stylesheet" href="{{ $customCmpConfig['theme_css_path'] ?? '/vendor/cmp/css/cookieconsent-theme.min.css' }}">
<style>
    .cm {
        {!! $themeCssVariables !!}
    }
</style>

{{-- Custom CMP library - always loaded for fallback support --}}
<script src="{{ $customCmpConfig['js_path'] ?? '/vendor/cmp/js/cookieconsent.umd.min.js' }}"></script>

{{-- CMP Configuration and Type --}}
<script>
    // Set CMP type - 'google' will wait for fallback event, 'custom' initializes immediately
    window.__cmpType = '{{ $useGoogleCmp ? 'google' : 'custom' }}';

    // Configuration for Custom CMP (used for both primary and fallback modes)
    window.__cmpConfig = {
        translationsPath: '{{ $customCmpConfig['translations_path'] ?? '/vendor/cmp/translations/' }}',
        supportedLanguages: {!! json_encode($customCmpConfig['supported_languages'] ?? ['en']) !!},
        cookieName: '{{ $customCmpConfig['cookie_name'] ?? 'cc_cookie' }}',
        cookieExpiryDays: {{ $customCmpConfig['cookie_expiry_days'] ?? 365 }},
        mode: '{{ $customCmpConfig['mode'] ?? 'opt-in' }}',
        disablePageInteraction: {{ ($customCmpConfig['disable_page_interaction'] ?? true) ? 'true' : 'false' }},
        guiOptions: {!! json_encode($customCmpConfig['gui_options'] ?? []) !!},
        categories: {!! json_encode($customCmpConfig['categories'] ?? []) !!},
        showRejectButton: {{ ($customCmpConfig['show_reject_button'] ?? true) ? 'true' : 'false' }},
        showSettingsButton: {{ ($customCmpConfig['show_settings_button'] ?? true) ? 'true' : 'false' }},
        settingsButtonPosition: '{{ $customCmpConfig['settings_button_position'] ?? 'bottom_left' }}',
        privacyPolicyUrl: {!! json_encode($customCmpConfig['privacy_policy_url'] ?? null) !!},
        termsUrl: {!! json_encode($customCmpConfig['terms_url'] ?? null) !!}
    };
</script>

{{-- Custom CMP initialization script - registers 'cmp.fallback' listener --}}
<script src="{{ $customCmpConfig['cmp_js_path'] ?? '/vendor/cmp/js/consent-cmp.min.js' }}"></script>

@if($useGoogleCmp)
{{--
    Google Funding Choices fallback detection
    IMPORTANT: This callback MUST be registered BEFORE the Google FC script loads
    to avoid race conditions. The callback queue is set up synchronously, then
    Google FC loads async and will call our callback when ready.

    Status values (numeric):
    - 0 = UNKNOWN (error/not ready) -> do nothing (Google might still show banner)
    - 1 = GRANTED -> fire consent.update
    - 2 = DENIED -> fire consent.update
    - 3 = NOT_APPLICABLE (Brazil/LGPD, etc.) -> fallback to Custom CMP
    - 4 = NOT_CONFIGURED -> do nothing (Google might still show banner)

    We ONLY fallback on status 3 to avoid showing double consent banners.
--}}
<script>
(function(){
    window.googlefc = window.googlefc || {};
    window.googlefc.callbackQueue = window.googlefc.callbackQueue || [];

    // Track if we've already handled consent (prevent duplicate processing)
    var consentHandled = false;

    // Register callback for when Consent Mode data is ready
    // IMPORTANT: Use CONSENT_MODE_DATA_READY, NOT CONSENT_DATA_READY (deprecated)
    window.googlefc.callbackQueue.push({
        CONSENT_MODE_DATA_READY: function() {
            if (consentHandled) return;

            if (typeof window.googlefc.getGoogleConsentModeValues === 'function') {
                var values = window.googlefc.getGoogleConsentModeValues();
                var status = values.analyticsStoragePurposeConsentStatus;

                // Status values are NUMERIC (not strings or constants)
                // Only fallback on status 3 (NOT_APPLICABLE) - this means Google
                // determined the region doesn't require consent (Brazil, etc.)
                // and NO banner will be shown. Status 0 (UNKNOWN) or 4 (NOT_CONFIGURED)
                // might still show Google's banner, so we don't want double banners.
                if (status === 3) {
                    // Google CMP doesn't apply in this region - show Custom CMP
                    window.dispatchEvent(new CustomEvent('cmp.fallback'));
                    consentHandled = true;
                }
                else if (status === 1 || status === 2) {
                    // Google CMP handled consent - status 1=GRANTED, 2=DENIED
                    var granted = (status === 1);

                    // Initialize/update consent state
                    window.__consentState = window.__consentState || {
                        ad_storage: 'denied',
                        analytics_storage: 'denied',
                        ad_user_data: 'denied',
                        ad_personalization: 'denied',
                        functionality_storage: 'denied',
                        personalization_storage: 'denied',
                        security_storage: 'granted'
                    };

                    window.__consentState.analytics_storage = granted ? 'granted' : 'denied';
                    window.__consentState.ad_storage = (values.adStoragePurposeConsentStatus === 1) ? 'granted' : 'denied';
                    window.__consentState.ad_user_data = (values.adUserDataPurposeConsentStatus === 1) ? 'granted' : 'denied';
                    window.__consentState.ad_personalization = (values.adPersonalizationPurposeConsentStatus === 1) ? 'granted' : 'denied';

                    // Update gtag consent
                    if (typeof gtag === 'function') {
                        gtag('consent', 'update', {
                            ad_storage: window.__consentState.ad_storage,
                            analytics_storage: window.__consentState.analytics_storage,
                            ad_user_data: window.__consentState.ad_user_data,
                            ad_personalization: window.__consentState.ad_personalization
                        });
                    }

                    // Fire consent.update event for GA4/WireBoard
                    window.dispatchEvent(new CustomEvent('consent.update', {
                        detail: window.__consentState
                    }));
                    consentHandled = true;
                }
            } else {
                // getGoogleConsentModeValues not available - fallback to Custom CMP
                window.dispatchEvent(new CustomEvent('cmp.fallback'));
                consentHandled = true;
            }
        }
    });
})();
</script>

{{-- Google Funding Choices - for sites with AdSense --}}
<script async src="https://fundingchoicesmessages.google.com/i/{{ $adsensePubId }}?ers=1"></script>
@endif
