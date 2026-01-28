/**
 * Custom CMP (Consent Management Platform) for non-AdSense sites
 * Uses vanilla-cookieconsent library with multilanguage support
 * Styled to match Google Funding Choices appearance
 *
 * @package wireboard/laravel-cmp
 */
(function initCustomCMP() {
    'use strict';

    // Only run if we're using custom CMP (not Google Funding Choices)
    if (typeof CookieConsent === 'undefined' || window.__cmpType !== 'custom') {
        if (window.__cmpType === 'custom') {
            setTimeout(initCustomCMP, 50);
        }
        return;
    }

    // Get configuration from window (set by Blade component)
    var config = window.__cmpConfig || {};

    // Get current locale from HTML lang attribute
    var currentLocale = document.documentElement.lang || 'en';

    // Supported locales - configurable
    var supportedLocales = config.supportedLanguages || [
        'en', 'de', 'fr', 'es', 'it', 'nl', 'pt',
        'pl', 'da', 'sv', 'no', 'fi', 'hu'
    ];

    if (supportedLocales.indexOf(currentLocale) === -1) {
        currentLocale = 'en';
    }

    // Build translations path
    var translationsPath = config.translationsPath || '/vendor/cmp/translations/';

    // Load translation first, then initialize CookieConsent
    fetch(translationsPath + currentLocale + '.json')
        .then(function(response) {
            if (!response.ok) throw new Error('Translation not found');
            return response.json();
        })
        .catch(function() {
            // Fallback to English
            return fetch(translationsPath + 'en.json').then(function(r) { return r.json(); });
        })
        .then(function(translation) {
            initializeCookieConsent(translation, currentLocale, config);
        })
        .catch(function(error) {
            console.error('GDPR Consent: Failed to load translations', error);
            // Initialize with default English strings
            initializeCookieConsent(getDefaultTranslation(), 'en', config);
        });

    /**
     * Initialize CookieConsent with loaded translations
     */
    function initializeCookieConsent(translation, locale, config) {
        // Build translations object
        var translations = {};
        translations[locale] = translation;

        // Build categories from config
        var categories = buildCategories(config.categories || {});

        // GUI options from config
        var guiOptions = config.guiOptions || {};
        var consentModal = guiOptions.consentModal || {};
        var preferencesModal = guiOptions.preferencesModal || {};

        // Initialize CookieConsent
        CookieConsent.run({
            // Cookie settings
            cookie: {
                name: config.cookieName || 'cc_cookie',
                expiresAfterDays: config.cookieExpiryDays || 365
            },

            // Categories
            categories: categories,

            // Language configuration with loaded translations
            language: {
                default: locale,
                autoDetect: 'document',
                translations: translations
            },

            // GUI Options - Google-like centered modal
            guiOptions: {
                consentModal: {
                    layout: consentModal.layout || 'box inline',
                    position: consentModal.position || 'middle center',
                    equalWeightButtons: consentModal.equalWeightButtons !== false,
                    flipButtons: consentModal.flipButtons !== false
                },
                preferencesModal: {
                    layout: preferencesModal.layout || 'box',
                    position: preferencesModal.position || 'middle center',
                    equalWeightButtons: preferencesModal.equalWeightButtons !== false,
                    flipButtons: preferencesModal.flipButtons !== false
                }
            },

            // Force user interaction (blocking mode)
            mode: config.mode || 'opt-in',
            disablePageInteraction: config.disablePageInteraction !== false,

            // Callbacks
            onConsent: function(param) {
                handleConsentChange(param);
            },
            onChange: function(param) {
                // Page reload required when analytics preference changes
                // because GA4 cannot be unloaded once loaded
                if (param.changedCategories && param.changedCategories.includes('analytics')) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 100);
                    return;
                }
                handleConsentChange(param);
            }
        });
    }

    /**
     * Build cookie categories from config
     */
    function buildCategories(configCategories) {
        var categories = {};

        // Default necessary category
        categories.necessary = {
            enabled: true,
            readOnly: true
        };

        // Default analytics category
        categories.analytics = {
            enabled: true,
            readOnly: false,
            autoClear: {
                cookies: [
                    { name: /^_ga/ },
                    { name: '_gid' },
                    { name: /^_wb_/ },
                    { name: /^_sp_/ }
                ]
            }
        };

        // Override with config
        if (configCategories.necessary) {
            categories.necessary = {
                enabled: configCategories.necessary.enabled !== false,
                readOnly: configCategories.necessary.readOnly !== false
            };
        }

        if (configCategories.analytics) {
            categories.analytics = {
                enabled: configCategories.analytics.enabled !== false,
                readOnly: configCategories.analytics.readOnly === true,
                autoClear: buildAutoClear(configCategories.analytics.autoClear)
            };
        }

        // Add additional categories from config
        Object.keys(configCategories).forEach(function(key) {
            if (key !== 'necessary' && key !== 'analytics') {
                var cat = configCategories[key];
                categories[key] = {
                    enabled: cat.enabled === true,
                    readOnly: cat.readOnly === true
                };
                if (cat.autoClear) {
                    categories[key].autoClear = buildAutoClear(cat.autoClear);
                }
            }
        });

        return categories;
    }

    /**
     * Build autoClear config from cookie patterns
     */
    function buildAutoClear(patterns) {
        if (!patterns || !Array.isArray(patterns)) {
            return undefined;
        }

        return {
            cookies: patterns.map(function(pattern) {
                // If pattern starts/ends with /, treat as regex
                if (typeof pattern === 'string' && pattern.startsWith('/') && pattern.endsWith('/')) {
                    return { name: new RegExp(pattern.slice(1, -1)) };
                }
                return { name: pattern };
            })
        };
    }

    /**
     * Handle consent changes and update our consent state
     */
    function handleConsentChange(param) {
        if (!param) {
            console.error('Custom CMP: Invalid parameter object');
            return;
        }

        // vanilla-cookieconsent v3 structure
        var acceptedCategories = [];

        if (param.cookie && param.cookie.categories) {
            acceptedCategories = param.cookie.categories;
        } else if (param.categories) {
            acceptedCategories = param.categories;
        } else if (param.level) {
            acceptedCategories = param.level;
        }

        // Check if analytics category is accepted
        var analyticsAccepted = Array.isArray(acceptedCategories)
            ? acceptedCategories.indexOf('analytics') !== -1
            : false;

        // Check if marketing category is accepted (if exists)
        var marketingAccepted = Array.isArray(acceptedCategories)
            ? acceptedCategories.indexOf('marketing') !== -1
            : false;

        // Initialize consent state if needed
        window.__consentState = window.__consentState || {
            ad_storage: 'denied',
            analytics_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied'
        };

        // Update analytics consent
        if (analyticsAccepted) {
            window.__consentState.analytics_storage = 'granted';
        } else {
            window.__consentState.analytics_storage = 'denied';
        }

        // Update ad consent (if marketing category exists)
        if (marketingAccepted) {
            window.__consentState.ad_storage = 'granted';
            window.__consentState.ad_user_data = 'granted';
            window.__consentState.ad_personalization = 'granted';
        }

        // Update Google's consent mode if gtag exists
        if (typeof gtag === 'function') {
            gtag('consent', 'update', {
                analytics_storage: window.__consentState.analytics_storage,
                ad_storage: window.__consentState.ad_storage,
                ad_user_data: window.__consentState.ad_user_data,
                ad_personalization: window.__consentState.ad_personalization
            });
        }

        // Fire event for WireBoard/GA to react
        // Use the global function if available (set by consent-tracker)
        if (typeof window.__cmpUpdateConsent === 'function') {
            window.__cmpUpdateConsent(window.__consentState);
        } else {
            // Fallback: dispatch event directly
            window.dispatchEvent(new CustomEvent('consent.update', {
                detail: window.__consentState
            }));
        }
    }

    /**
     * Get default English translation (fallback)
     */
    function getDefaultTranslation() {
        return {
            consentModal: {
                title: 'We use cookies',
                description: 'We use cookies and similar technologies to improve your experience and analyze traffic. You can choose your preferences below.',
                acceptAllBtn: 'Accept all',
                showPreferencesBtn: 'Manage preferences'
            },
            preferencesModal: {
                title: 'Cookie preferences',
                acceptAllBtn: 'Accept all',
                savePreferencesBtn: 'Save preferences',
                closeIconLabel: 'Close',
                sections: [
                    {
                        title: 'Necessary cookies',
                        description: 'These cookies are essential for the website to function properly. They cannot be disabled.',
                        linkedCategory: 'necessary'
                    },
                    {
                        title: 'Analytics cookies',
                        description: 'These cookies help us understand how visitors interact with our website by collecting anonymous information.',
                        linkedCategory: 'analytics'
                    }
                ]
            }
        };
    }
})();
