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
        |
        | With show_reject_button enabled:
        |   Consent Modal:     [Reject all] [Manage preferences] [Accept all]
        |   Preferences Modal: [Reject all] [Save preferences]   [Accept all]
        |
        | With show_reject_button disabled:
        |   Consent Modal:     [Manage preferences] [Accept all]
        |   Preferences Modal: [Save preferences]   [Accept all]
        |
        */
        'gui_options' => [
            'consent_modal' => [
                'layout' => 'box inline',       // 'box', 'box inline', 'cloud', 'bar'
                'position' => 'middle center',  // 'top', 'middle', 'bottom' + 'left', 'center', 'right'
                'equal_weight_buttons' => true,
                'flip_buttons' => true,         // true = [secondary] [primary]
            ],
            'preferences_modal' => [
                'layout' => 'box',
                'position' => 'middle center',
                'equal_weight_buttons' => true,
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
        'wait_for_update' => 500, // ms to wait for CMP to load
    ],
];
