<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable/Disable CMP
    |--------------------------------------------------------------------------
    |
    | Master switch to enable or disable the entire consent management platform.
    | When disabled, no consent scripts, trackers, or analytics will be loaded.
    |
    */
    'enabled' => env('CMP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Google AdSense (Site-Wide)
    |--------------------------------------------------------------------------
    |
    | When enabled, uses Google Funding Choices as the CMP.
    | When disabled, uses vanilla-cookieconsent (custom CMP).
    |
    */
    'adsense' => [
        'enabled' => env('CMP_ADSENSE_ENABLED', false),
        'pub_id' => env('CMP_ADSENSE_PUB_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4
    |--------------------------------------------------------------------------
    |
    | GA4 only loads after explicit user consent (GDPR requirement).
    | Due to EU DPA rulings, GA4 requires consent even in cookieless mode.
    |
    */
    'google_analytics' => [
        'enabled' => env('CMP_GA4_ENABLED', false),
        'measurement_id' => env('CMP_GA4_ID'),
        'cookie_flags' => 'SameSite=Lax',

        /*
        | Send our own page_view on client-side navigation.
        |
        | Leave this off unless you have switched "page changes based on
        | browser history events" OFF in the GA4 UI (Admin > Data streams >
        | Enhanced measurement). That option is enabled by default and already
        | reports SPA navigations, so turning both on double-counts them.
        */
        'spa_page_views' => env('CMP_GA4_SPA_PAGE_VIEWS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | WireBoard Analytics
    |--------------------------------------------------------------------------
    |
    | WireBoard can run in two loading modes:
    | - 'cookieless_first': Load immediately in cookieless mode, upgrade after consent
    | - 'consent_required': Only load after user grants analytics consent (like GA4)
    |
    */
    'wireboard' => [
        'enabled' => env('CMP_WIREBOARD_ENABLED', false),
        'loading_mode' => env('CMP_WIREBOARD_LOADING_MODE', 'cookieless_first'), // 'cookieless_first' or 'consent_required'
        'pipeline' => env('CMP_WIREBOARD_PIPELINE', 'pipeline-0.collector.wireboard.io'),
        'app_id' => env('CMP_WIREBOARD_APP_ID'),
        'publisher' => env('CMP_WIREBOARD_PUBLISHER'),
        'js_url' => env('CMP_WIREBOARD_JS_URL', 'https://static.wireboard.io/wireboard.js'),
        'load_events_script' => env('CMP_WIREBOARD_LOAD_EVENTS', false), // Load events.min.js for automatic event tracking
        'events_js_url' => env('CMP_WIREBOARD_EVENTS_JS_URL', 'https://static.wireboard.io/events.min.js'),
        'initialization_timeout' => 2000, // ms - fallback timeout if no consent interaction

        /*
        | Tracker contexts attached to every event.
        |
        | performance_timing reads the deprecated window.performance.timing of
        | the *document* load. On a single-page app that snapshot describes the
        | first page for the rest of the session, and collectors that post-
        | process it have been seen throwing on navigations that carry no fresh
        | navigation-timing entry. Turn it off for SPA hosts.
        */
        'contexts' => [
            'performance_timing' => env('CMP_WIREBOARD_PERFORMANCE_TIMING', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Single-page application support
    |--------------------------------------------------------------------------
    |
    | Inertia, Turbo, Livewire and client-side routers replace the page without
    | a document load, so a tracker that sends its page view once at boot
    | records an entire session as a single page. This bridge turns each
    | client-side navigation back into a page view for every enabled tracker.
    |
    | It is inert on a classic multi-page app: none of these events fire and
    | the URL only changes through a real document load.
    |
    | Hosts can also trigger one by hand:  window.cmpTrackPageView()
    |
    */
    'spa' => [
        'enabled' => env('CMP_SPA_TRACKING', true),

        // Framework navigation events listened for on `document` and `window`.
        'events' => [
            'inertia:navigate',   // Inertia.js
            'turbo:load',         // Hotwire Turbo
            'livewire:navigated', // Livewire wire:navigate
            'page:load',          // Turbolinks (legacy)
        ],

        // Also watch the History API, which covers routers that fire no event
        // of their own (React Router, Vue Router, plain pushState).
        'watch_history' => true,

        // Whether the fragment is part of the address.
        //
        // Off by default. On a classic site `#pricing` is a jump within the
        // page, not a new one, and only Chromium reports it as a navigation,
        // so counting it would split the numbers by browser. Turn it on for a
        // hash router, where `#/dashboard` really is the address.
        'hash_routing' => false,

        // How long to wait for <title> to settle before reporting, in
        // milliseconds. The report goes out as soon as the title changes, so
        // this is only the ceiling for pages that keep the same title.
        'title_wait' => 250,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom CMP Settings (vanilla-cookieconsent)
    |--------------------------------------------------------------------------
    |
    | Used when adsense.enabled = false.
    | These settings control the appearance and behavior of the consent modal.
    |
    */
    'custom_cmp' => [
        /*
        |----------------------------------------------------------------------
        | Asset Paths
        |----------------------------------------------------------------------
        |
        | Paths to the published assets. These are relative to the public directory.
        | After publishing, you can customize these files as needed.
        |
        */
        'css_path' => '/vendor/cmp/css/cookieconsent.min.css',
        'theme_css_path' => '/vendor/cmp/css/cookieconsent-theme.min.css',
        'js_path' => '/vendor/cmp/js/cookieconsent.umd.min.js',
        'cmp_js_path' => '/vendor/cmp/js/consent-cmp.min.js',
        'translations_path' => '/vendor/cmp/translations/',

        /*
        |----------------------------------------------------------------------
        | Supported Languages
        |----------------------------------------------------------------------
        |
        | Languages available for the consent modal.
        | Add more by publishing the translations and creating new JSON files.
        |
        */
        'supported_languages' => [
            'en', 'fr', 'de', 'es', 'it', 'nl', 'pt',
            'pl', 'da', 'sv', 'no', 'fi', 'hu',
        ],

        /*
        |----------------------------------------------------------------------
        | Cookie Settings
        |----------------------------------------------------------------------
        */
        'cookie_name' => 'cc_cookie',
        'cookie_expiry_days' => 365,

        /*
        |----------------------------------------------------------------------
        | GUI Options
        |----------------------------------------------------------------------
        |
        | Customize how buttons appear in the consent and preferences modals.
        | "Manage preferences" is styled as a text link (not a button).
        | On mobile, buttons stack vertically with Accept all on top.
        |
        | Desktop (show_reject_button = true):
        |   Consent Modal:      [ Reject all ]  [ Accept all ]
        |                          Manage preferences
        |   Preferences Modal:  [ Save preferences ] [ Reject all ] [ Accept all ]
        |
        | Desktop (show_reject_button = false):
        |   Consent Modal:      [ Manage preferences ] [ Accept all ]
        |   Preferences Modal:  [ Save preferences ] [ Accept all ]
        |
        */
        'gui_options' => [
            'consent_modal' => [
                'layout' => 'box inline',       // 'box', 'box inline', 'cloud', 'bar'
                'position' => 'middle center',  // 'top', 'middle', 'bottom' + 'left', 'center', 'right'
                'equal_weight_buttons' => false,
                'flip_buttons' => true,
            ],
            'preferences_modal' => [
                'layout' => 'box',
                'position' => 'middle center',
                'equal_weight_buttons' => false,
                'flip_buttons' => true,
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Show Reject Button
        |----------------------------------------------------------------------
        |
        | When enabled, shows a "Reject all" button on both modals.
        | This allows users to quickly reject all non-essential cookies.
        |
        */
        'show_reject_button' => true,

        /*
        |----------------------------------------------------------------------
        | Floating Settings Button
        |----------------------------------------------------------------------
        |
        | When enabled, shows a floating cookie icon button after the user
        | has made their consent choice. Clicking it reopens the preferences.
        |
        */
        'show_settings_button' => true,
        'settings_button_position' => 'bottom_left', // 'bottom_left' or 'bottom_right'

        /*
        |----------------------------------------------------------------------
        | Legal Links
        |----------------------------------------------------------------------
        |
        | Add links to your privacy policy and terms of service.
        | These will appear in the footer of the consent modal.
        | Leave empty or null to hide.
        |
        */
        'privacy_policy_url' => null,
        'terms_url' => null,

        /*
        |----------------------------------------------------------------------
        | Consent Mode
        |----------------------------------------------------------------------
        |
        | 'opt-in' requires explicit consent before analytics run.
        | 'opt-out' would allow analytics by default (not GDPR compliant).
        |
        */
        'mode' => 'opt-in',
        'disable_page_interaction' => true, // Blocking mode - user must interact

        /*
        |----------------------------------------------------------------------
        | Cookie Categories
        |----------------------------------------------------------------------
        |
        | Define which cookie categories to show. Each category can have:
        | - enabled: bool (pre-checked by default)
        | - read_only: bool (cannot be disabled)
        | - auto_clear: array of cookie patterns to clear when disabled
        |
        */
        'categories' => [
            'necessary' => [
                'enabled' => true,
                'read_only' => true,
            ],
            'analytics' => [
                'enabled' => true,  // Pre-toggled ON
                'read_only' => false,
                'auto_clear' => [
                    '/^_ga/',       // Google Analytics
                    '_gid',         // Google Analytics
                    '/^_wb_/',      // WireBoard
                    '/^_sp_/',      // WireBoard (previous versions)
                ],
            ],
            // Uncomment to add marketing cookies category:
            // 'marketing' => [
            //     'enabled' => false,
            //     'read_only' => false,
            //     'auto_clear' => ['/^_fbp/', '/^_gcl/', '/^_fbc/'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme Colors
    |--------------------------------------------------------------------------
    |
    | Google-like appearance by default. Override to match your brand.
    | These are used to generate CSS custom properties.
    |
    */
    'theme' => [
        'primary_bg' => '#1a73e8',           // Primary button background (Google blue)
        'primary_hover_bg' => '#1557b0',     // Primary button hover
        'primary_color' => '#ffffff',        // Primary button text
        'secondary_bg' => 'transparent',     // Secondary button background
        'secondary_border' => '#dadce0',     // Secondary button border
        'secondary_color' => '#1a73e8',      // Secondary button text
        'secondary_hover_bg' => '#f8f9fa',   // Secondary button hover
        'modal_bg' => '#ffffff',             // Modal background
        'text_color' => '#202124',           // Main text color
        'separator_color' => '#e8eaed',      // Section separators
        'border_radius' => '8px',            // Modal border radius
        'toggle_on_bg' => '#1a73e8',         // Toggle switch when ON
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent Mode v2 Defaults
    |--------------------------------------------------------------------------
    |
    | Default consent state before user interaction.
    | These should be 'denied' for GDPR compliance.
    |
    */
    'consent_defaults' => [
        'ad_storage' => 'denied',
        'analytics_storage' => 'denied',
        'ad_user_data' => 'denied',
        'ad_personalization' => 'denied',
        'functionality_storage' => 'denied',
        'personalization_storage' => 'denied',
        'security_storage' => 'granted', // strictly necessary, exempt from consent
        'wait_for_update' => 500, // ms to wait for CMP to load
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent Mode v2 Behavior
    |--------------------------------------------------------------------------
    |
    | ads_data_redaction: when ad_storage is denied, redact ads click
    | identifiers (gclid, dclid) from network requests.
    | url_passthrough: pass ad click information through URLs when cookies
    | are denied (improves conversion measurement without cookies).
    |
    */
    'ads_data_redaction' => env('CMP_ADS_DATA_REDACTION', true),
    'url_passthrough' => env('CMP_URL_PASSTHROUGH', false),
];
