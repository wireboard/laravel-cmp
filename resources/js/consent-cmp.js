/**
 * Custom CMP (Consent Management Platform) for non-AdSense sites
 * Uses vanilla-cookieconsent library with multilanguage support
 * Styled to match Google Funding Choices appearance
 *
 * Also serves as fallback for Google Funding Choices in regions where
 * Google doesn't show consent banners (e.g., Brazil/LGPD, South Africa/POPIA).
 *
 * @package wireboard/laravel-cmp
 */
(function() {
    'use strict';

    /**
     * Main initialization function for Custom CMP
     * Called either immediately (for custom CMP mode) or via cmp.fallback event (for Google CMP fallback)
     */
    function initCustomCMP() {
        // Prevent double initialization
        if (window.__customCMPInitialized) {
            return;
        }

        // Wait for CookieConsent library to be available
        if (typeof CookieConsent === 'undefined') {
            setTimeout(initCustomCMP, 50);
            return;
        }

        // Mark as initialized
        window.__customCMPInitialized = true;

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
            // Remove reject button if disabled in config
            if (config.showRejectButton === false) {
                if (translation.consentModal) {
                    delete translation.consentModal.acceptNecessaryBtn;
                }
                if (translation.preferencesModal) {
                    delete translation.preferencesModal.acceptNecessaryBtn;
                }
            }

            // Add legal links footer if URLs provided
            var footerLinks = buildFooterLinks(config, locale);
            if (footerLinks && translation.consentModal) {
                translation.consentModal.footer = footerLinks;
            }

            // Build translations object
            var translations = {};
            translations[locale] = translation;

            // Build categories from config
            var categories = buildCategories(config.categories || {});

            // GUI options from config (PHP uses snake_case)
            var guiOptions = config.guiOptions || {};
            var consentModal = guiOptions.consent_modal || guiOptions.consentModal || {};
            var preferencesModal = guiOptions.preferences_modal || guiOptions.preferencesModal || {};

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

                // GUI Options - respect user config, default to library defaults
                guiOptions: {
                    consentModal: {
                        layout: consentModal.layout || 'box inline',
                        position: consentModal.position || 'middle center',
                        equalWeightButtons: consentModal.equal_weight_buttons === true || consentModal.equalWeightButtons === true,
                        flipButtons: consentModal.flip_buttons === true || consentModal.flipButtons === true
                    },
                    preferencesModal: {
                        layout: preferencesModal.layout || 'box',
                        position: preferencesModal.position || 'middle center',
                        equalWeightButtons: preferencesModal.equal_weight_buttons === true || preferencesModal.equalWeightButtons === true,
                        flipButtons: preferencesModal.flip_buttons === true || preferencesModal.flipButtons === true
                    }
                },

                // Force user interaction (blocking mode)
                mode: config.mode || 'opt-in',
                disablePageInteraction: config.disablePageInteraction !== false,

                // Callbacks
                onConsent: function(param) {
                    handleConsentChange(param);
                    showSettingsButton(config);
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
                },
                onFirstConsent: function() {
                    showSettingsButton(config);
                }
            });

            // Show settings button if consent was already given
            if (CookieConsent.validConsent()) {
                showSettingsButton(config);
            }
        }

        /**
         * Create and show the floating settings button
         */
        function showSettingsButton(config) {
            if (config.showSettingsButton === false) {
                return;
            }

            // Check if button already exists
            if (document.getElementById('cmp-settings-btn')) {
                document.getElementById('cmp-settings-btn').classList.add('visible');
                return;
            }

            // Create button
            var btn = document.createElement('button');
            btn.id = 'cmp-settings-btn';
            btn.className = 'cmp-settings-btn';
            btn.setAttribute('aria-label', 'Cookie settings');
            btn.setAttribute('title', 'Cookie settings');

            // Position class
            var position = config.settingsButtonPosition || 'bottom_left';
            btn.classList.add(position === 'bottom_right' ? 'bottom-right' : 'bottom-left');

            // Cookie icon SVG
            btn.innerHTML = '<svg viewBox="0 0 550 550" xmlns="http://www.w3.org/2000/svg"><path d="M428.9,181.5c-6.1,6.9-13.1,10.9-20.9,11.8c-10.8,1.3-20.2-3.6-25.3-7c-6.8,10.4-15.1,16.4-24.7,18c-1.8,0.3-3.5,0.4-5.2,0.4c-12.4,0-22.7-7.1-26.5-10c-13.3,1.1-24-2.1-31.9-9.4c-9.4-8.7-12.2-21.1-13-28.3c-12.5,3.3-23.1,2.6-31.4-2.3c-9.2-5.4-12.9-14.4-14.1-18.6c-18-8.4-25-20.7-27.4-31.1C142,132,97.8,197.4,97.8,269.4c0,97.7,79.5,177.2,177.2,177.2s177.2-79.5,177.2-177.2C452.2,238.4,444.2,208.1,428.9,181.5z M201.1,323.2c-7.3,0-13.2-5.9-13.2-13.2s5.9-13.2,13.2-13.2c7.3,0,13.2,5.9,13.2,13.2S208.4,323.2,201.1,323.2z M201.1,237.3c-13,0-23.5-10.5-23.5-23.5s10.5-23.5,23.5-23.5s23.5,10.5,23.5,23.5S214.1,237.3,201.1,237.3z M264.1,418.2c-11.7,0-21.2-9.5-21.2-21.2s9.5-21.2,21.2-21.2c11.7,0,21.2,9.5,21.2,21.2C285.3,408.8,275.8,418.2,264.1,418.2z M288.2,306c-7.3,0-13.2-5.9-13.2-13.2c0-7.3,5.9-13.2,13.2-13.2c7.3,0,13.2,5.9,13.2,13.2C301.4,300.1,295.4,306,288.2,306z M385.5,354.1c-13,0-23.5-10.5-23.5-23.5s10.5-10.5,23.5-23.5s23.5,10.5,23.5,23.5S398.5,354.1,385.5,354.1z"/></svg>';

            // Click handler
            btn.addEventListener('click', function() {
                CookieConsent.showPreferences();
            });

            document.body.appendChild(btn);

            // Fade in after a short delay
            setTimeout(function() {
                btn.classList.add('visible');
            }, 100);
        }

        /**
         * Build footer links HTML for legal pages
         */
        function buildFooterLinks(config, locale) {
            var links = [];

            // Get localized link texts
            var linkTexts = {
                en: { privacy: 'Privacy Policy', terms: 'Terms of Service' },
                fr: { privacy: 'Politique de confidentialité', terms: 'Conditions d\'utilisation' },
                de: { privacy: 'Datenschutzrichtlinie', terms: 'Nutzungsbedingungen' },
                es: { privacy: 'Política de privacidad', terms: 'Términos de servicio' },
                it: { privacy: 'Informativa sulla privacy', terms: 'Termini di servizio' },
                nl: { privacy: 'Privacybeleid', terms: 'Servicevoorwaarden' },
                pt: { privacy: 'Política de privacidade', terms: 'Termos de serviço' },
                pl: { privacy: 'Polityka prywatności', terms: 'Warunki usługi' },
                da: { privacy: 'Privatlivspolitik', terms: 'Servicevilkår' },
                sv: { privacy: 'Integritetspolicy', terms: 'Användarvillkor' },
                no: { privacy: 'Personvernerklæring', terms: 'Tjenestevilkår' },
                fi: { privacy: 'Tietosuojakäytäntö', terms: 'Käyttöehdot' },
                hu: { privacy: 'Adatvédelmi irányelvek', terms: 'Szolgáltatási feltételek' }
            };

            var texts = linkTexts[locale] || linkTexts.en;

            if (config.privacyPolicyUrl) {
                links.push('<a href="' + config.privacyPolicyUrl + '" target="_blank" class="cmp-legal-link">' + texts.privacy + '</a>');
            }

            if (config.termsUrl) {
                links.push('<a href="' + config.termsUrl + '" target="_blank" class="cmp-legal-link">' + texts.terms + '</a>');
            }

            return links.length > 0 ? '<span class="cmp-legal-links">' + links.join('') + '</span>' : null;
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

            // Check if functionality/preferences category is accepted (if exists)
            var functionalityAccepted = Array.isArray(acceptedCategories)
                ? (acceptedCategories.indexOf('functionality') !== -1 || acceptedCategories.indexOf('preferences') !== -1)
                : false;

            // Initialize consent state if needed
            window.__consentState = window.__consentState || {
                ad_storage: 'denied',
                analytics_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
                functionality_storage: 'denied',
                personalization_storage: 'denied',
                security_storage: 'granted'
            };

            // Update analytics consent
            window.__consentState.analytics_storage = analyticsAccepted ? 'granted' : 'denied';

            // Update ad consent (covers grant and revoke)
            window.__consentState.ad_storage = marketingAccepted ? 'granted' : 'denied';
            window.__consentState.ad_user_data = marketingAccepted ? 'granted' : 'denied';
            window.__consentState.ad_personalization = marketingAccepted ? 'granted' : 'denied';

            // Update functionality consent (covers grant and revoke)
            window.__consentState.functionality_storage = functionalityAccepted ? 'granted' : 'denied';
            window.__consentState.personalization_storage = functionalityAccepted ? 'granted' : 'denied';

            // Update Google's consent mode if gtag exists
            if (typeof gtag === 'function') {
                gtag('consent', 'update', {
                    analytics_storage: window.__consentState.analytics_storage,
                    ad_storage: window.__consentState.ad_storage,
                    ad_user_data: window.__consentState.ad_user_data,
                    ad_personalization: window.__consentState.ad_personalization,
                    functionality_storage: window.__consentState.functionality_storage,
                    personalization_storage: window.__consentState.personalization_storage,
                    security_storage: window.__consentState.security_storage
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
    }

    // =========================================================================
    // INITIALIZATION LOGIC
    // =========================================================================

    // Initialize immediately if Custom CMP mode (non-AdSense sites)
    if (window.__cmpType === 'custom') {
        initCustomCMP();
    }

    // Listen for fallback trigger from Google CMP
    // This fires when Google Funding Choices returns status 0, 3, or 4
    // (UNKNOWN, NOT_APPLICABLE, or NOT_CONFIGURED)
    // This handles regions like Brazil/LGPD where Google doesn't show banners
    window.addEventListener('cmp.fallback', function() {
        window.__cmpType = 'custom';
        initCustomCMP();
    });
})();
