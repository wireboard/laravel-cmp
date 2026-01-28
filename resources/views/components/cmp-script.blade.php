{{-- CMP Script - loads on ALL pages --}}
@if($useGoogleCmp)
    {{-- Google Funding Choices - for sites with AdSense --}}
    <script async src="https://fundingchoicesmessages.google.com/i/{{ $adsensePubId }}?ers=1"></script>
@else
    {{-- Custom CMP - vanilla-cookieconsent --}}
    <link rel="stylesheet" href="{{ $customCmpConfig['css_path'] ?? '/vendor/cmp/css/cookieconsent.min.css' }}">
    <link rel="stylesheet" href="{{ $customCmpConfig['theme_css_path'] ?? '/vendor/cmp/css/cookieconsent-theme.min.css' }}">
    <style>
        .cm {
            {!! $themeCssVariables !!}
        }
    </style>
    <script src="{{ $customCmpConfig['js_path'] ?? '/vendor/cmp/js/cookieconsent.umd.min.js' }}"></script>
    {{-- Pass configuration to JavaScript --}}
    <script>
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
@endif
