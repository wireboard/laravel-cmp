# WireBoard Laravel CMP

A consent management platform for Laravel with support for [WireBoard Analytics](https://wireboard.io), Google Analytics 4, and Google Funding Choices.

![WireBoard Laravel CMP](wireboard-laravel-cmp.png)

> **Disclaimer:** This package provides tools to help manage user consent. It is your responsibility to ensure your implementation meets applicable privacy regulations (GDPR, ePrivacy, etc.) for your jurisdiction. Consult with a legal professional for compliance advice.

## Features

- **Consent Mode v2** - The full signal set (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`, `functionality_storage`, `personalization_storage`, `security_storage`) denied by default until user consent, with optional `ads_data_redaction` and `url_passthrough`
- **Flexible CMP Options** - Google Funding Choices (for AdSense sites) or vanilla-cookieconsent (custom CMP)
- **Automatic Regional Fallback** - Falls back to Custom CMP in regions where Google doesn't show banners (Brazil, South Africa, etc.)
- **Google Analytics 4** - Consent-gated loading (only loads after user consent)
- **[WireBoard Analytics](https://wireboard.io)** - Configurable loading mode (cookieless first or consent required)
- **Third-Party Scripts** - Load any analytics/marketing scripts after consent with `<x-cmp::on-consent>`
- **Fully Configurable** - Theme colors, button layouts, cookie categories, languages
- **Reject All Button** - Optional "Reject all" button for quick opt-out
- **Floating Settings Button** - Cookie icon button to reopen preferences after consent
- **13 Languages** - English, French, German, Spanish, Italian, Dutch, Portuguese, Polish, Danish, Swedish, Norwegian, Finnish, Hungarian
- **Publishable Assets** - Customize CSS, JS, translations, and views
- **SPA page views** - Inertia, Turbo, Livewire and History-API navigations are tracked, not just the first load

## Single-page applications (Inertia, Turbo, Livewire, client routers)

Analytics tags send their page view once, when the document loads. An SPA then
replaces the page without ever loading another document, so **an entire session
is recorded as a single page view**.

The package ships a bridge that turns each client-side navigation back into a
page view for every enabled tracker. It is included in `<x-cmp::scripts />` and
is on by default. A classic multi-page app is unaffected: none of these events
fire there, and a link that loads a new document is already counted the usual
way. If such an app changes the URL with `pushState` of its own, for a filter
or for pagination, that does count as a view, which is normally what you want.
Set `watch_history` to `false` if it is not.

It listens for `inertia:navigate`, `turbo:load`, `livewire:navigated` and
`page:load`, and also watches the History API so routers that announce nothing
of their own (React Router, Vue Router, plain `pushState`) are covered too.
Where the browser exposes the Navigation API it is used directly, and the
History patch is only the fallback.

One navigation announces itself several times over: a framework event, its own
`pushState`, and the Navigation API. They all carry the same address, so the
bridge reports a view only when the address actually changed. There is
deliberately no time window: one would also swallow a genuine second navigation
made moments after the first.

The fragment is not part of that address. Clicking `#pricing` is a jump within
the page, and only Chromium's Navigation API calls it a navigation at all, so
counting it would report views that Firefox and Safari never send and split
your numbers by browser. Set `hash_routing` to `true` if you run a hash router,
where `#/dashboard` really is the address.

The report waits for `<title>` to settle, because frameworks set the title
while the new page mounts. It goes out on the first title change, or after
`title_wait` for a page that keeps the same title.

```php
// config/cmp.php
'spa' => [
    'enabled' => env('CMP_SPA_TRACKING', true),
    'events' => ['inertia:navigate', 'turbo:load', 'livewire:navigated', 'page:load'],
    'watch_history' => true,
    'hash_routing' => false,
    'title_wait' => 250,
],
```

Report a view the router never announced (a tab, a modal route, a filter that
changes no URL):

```js
window.cmpTrackPageView();
```

Every navigation also dispatches `cmp:pageview` on `window`, carrying
`{ url, referrer, title }`, so a host can hook its own tags onto the same
signal:

```js
window.addEventListener('cmp:pageview', (event) => {
    myTag('page', event.detail.url);
});
```

### The performance-timing context

WireBoard attaches a `performanceTiming` context built from the deprecated
`window.performance.timing` of the **document** load. On an SPA that snapshot
describes the first page for the rest of the session, and collectors that
post-process it have been seen throwing on navigations that carry no fresh
navigation-timing entry. SPA hosts should turn it off:

```dotenv
CMP_WIREBOARD_PERFORMANCE_TIMING=false
```

It stays on by default so existing installations keep collecting what they
always did.

## Upgrading from 1.5.x

Nothing breaks and no configuration is required, but two things are worth
knowing.

**If you published the views, re-publish them.** Published views take priority
over the package's own, so a `resources/views/vendor/cmp/scripts.blade.php`
from 1.5.x has no `<x-cmp-spa-bridge />` in it and you will get no SPA tracking
at all, with nothing to indicate why. Either re-publish:

```bash
php artisan vendor:publish --tag=cmp-views --force
```

or add the component yourself, last in the file, so the trackers above it have
already registered their listeners:

```blade
<x-cmp-spa-bridge />
```

Diff your other published views against the package before overwriting them if
you have customised any.

**Your published config keeps working untouched.** The package merges its
defaults under whatever you have published, so the new `spa` block arrives on
its own and SPA tracking turns on. The merge is shallow, though, which means
new keys *inside* a block you have already published do not reach you:
`wireboard.contexts.performance_timing` and `google_analytics.spa_page_views`
both fall back to their safe values instead. Re-publish the config, or copy
those keys across by hand, if you want them.

The same fallbacks apply if you deploy with `php artisan config:cache` and have
not re-cached yet, so an upgrade never changes behaviour before you are ready
for it.

GA4 is left alone by default. Enhanced Measurement already reports history
navigations on every data stream, so the package does not send its own unless
you opt in with `google_analytics.spa_page_views`.

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, 12.x, or 13.x

## Installation

```bash
composer require wireboard/laravel-cmp
```

The package auto-registers via Laravel's package discovery.

### Publish Assets

```bash
# Publish everything (recommended for first install)
php artisan vendor:publish --tag=cmp

# Or publish individually:
php artisan vendor:publish --tag=cmp-config       # Config file
php artisan vendor:publish --tag=cmp-assets       # JS/CSS files
php artisan vendor:publish --tag=cmp-translations # Translation files
php artisan vendor:publish --tag=cmp-views        # Blade views
php artisan vendor:publish --tag=cmp-source       # Source files (for custom builds)
php artisan vendor:publish --tag=cmp-react        # React components (Inertia.js)
php artisan vendor:publish --tag=cmp-vue          # Vue components (Inertia.js)
```

## Configuration

Add these environment variables to your `.env` file:

```env
# Enable/Disable CMP (default: true)
CMP_ENABLED=true

# Google AdSense (determines which CMP to use)
CMP_ADSENSE_ENABLED=false
CMP_ADSENSE_PUB_ID=pub-XXXXXXXXXX

# Google Analytics 4 (requires user consent)
CMP_GA4_ENABLED=true
CMP_GA4_ID=G-XXXXXXXXXX

# WireBoard Analytics
CMP_WIREBOARD_ENABLED=false
CMP_WIREBOARD_LOADING_MODE=cookieless_first  # 'cookieless_first' or 'consent_required'
CMP_WIREBOARD_PIPELINE=pipeline-0.collector.wireboard.io
CMP_WIREBOARD_APP_ID=your-app-id
CMP_WIREBOARD_PUBLISHER=your-publisher-id
```

## Usage

### Quick Start (All-in-One)

Add a single component in your layout's `<head>`:

```blade
<head>
    <x-cmp::scripts />

    <!-- Rest of your head content -->
</head>
```

### Individual Components

For more control, use individual components:

```blade
<head>
    {{-- 1. Consent Mode v2 Defaults - MUST be first --}}
    <x-cmp::consent-mode />

    {{-- 2. CMP Script (Google Funding Choices or vanilla-cookieconsent) --}}
    <x-cmp::cmp-script />

    {{-- 3. Consent State Tracker --}}
    <x-cmp::consent-tracker />

    {{-- 4. Google Analytics 4 (consent-gated) --}}
    <x-cmp::google-analytics />

    {{-- 5. WireBoard Analytics --}}
    <x-cmp::wireboard />

    <!-- Rest of your head content -->
</head>
```

### Cookie Preferences Link (Custom CMP Only)

Add a link to let users manage their cookie preferences.

#### Blade

```blade
<footer>
    <x-cmp::cookie-preferences-link />

    {{-- Or with custom text and class --}}
    <x-cmp::cookie-preferences-link
        text="Cookie Settings"
        class="text-sm text-gray-500 hover:underline"
    />
</footer>
```

#### React / Inertia.js

Publish the React component:

```bash
php artisan vendor:publish --tag=cmp-react
```

Then use it in your components:

```tsx
import { CookiePreferencesLink, useCookieConsent } from '@/components/CookiePreferencesLink';

// As a component
<CookiePreferencesLink text="Cookie Settings" className="text-gray-600" />

// Or use the hook
const { showPreferences, hasValidConsent } = useCookieConsent();
<button onClick={showPreferences}>Cookie Settings</button>
```

#### Vue / Inertia.js

Publish the Vue component:

```bash
php artisan vendor:publish --tag=cmp-vue
```

Then use it in your components:

```vue
<script setup>
import CookiePreferencesLink from '@/components/CookiePreferencesLink.vue';
// Or use the composable
import { useCookieConsent } from '@/composables/useCookieConsent';

const { showPreferences } = useCookieConsent();
</script>

<template>
    <CookiePreferencesLink text="Cookie Settings" class="text-gray-600" />
    <!-- Or with composable -->
    <button @click="showPreferences">Cookie Settings</button>
</template>
```

> **Note:** These components only work with the Custom CMP. For Google Funding Choices, users manage preferences through Google's built-in UI.

### Third-Party Scripts (On-Consent)

Load any third-party scripts only after the user grants consent. You can use either the Blade component or JavaScript approach.

#### Option 1: Blade Component

```blade
{{-- Load analytics script after consent --}}
<x-cmp::on-consent category="analytics">
    <script src="https://static.getclicky.com/js"></script>
    <script>try{ clicky.init(123456); }catch(e){}</script>
</x-cmp::on-consent>

{{-- Load marketing/ads script after consent --}}
<x-cmp::on-consent category="marketing">
    <script src="https://example.com/pixel.js"></script>
</x-cmp::on-consent>
```

**Built-in category mappings:**
- `analytics` - Maps to `analytics_storage` consent
- `marketing` (or `ads`) - Maps to `ad_storage` consent

You can use any category name you define in your `config/cmp.php`. See [Adding a Marketing Category](#adding-a-marketing-category) for a complete example.

#### Option 2: JavaScript Event Listener

```javascript
window.addEventListener('consent.update', function(e) {
    if (e.detail.analytics_storage === 'granted') {
        // Load Clicky, Matomo, Plausible, etc.
    }
    if (e.detail.ad_storage === 'granted') {
        // Load marketing/ads scripts
    }
});
```

Both approaches work with external scripts, inline scripts, and any HTML content.

## Configuration Options

After publishing the config, edit `config/cmp.php`:

### Enable/Disable CMP

```php
'enabled' => env('CMP_ENABLED', true),
```

### AdSense / CMP Mode Selection

This setting determines which CMP is used:

```php
'adsense' => [
    'enabled' => env('CMP_ADSENSE_ENABLED', false),  // true = Google Funding Choices, false = Custom CMP
    'pub_id' => env('CMP_ADSENSE_PUB_ID'),           // Your AdSense publisher ID (pub-XXXXXXXXXX)
],
```

### WireBoard Loading Mode

Choose how WireBoard loads relative to user consent:

```php
'wireboard' => [
    'enabled' => true,
    'loading_mode' => 'cookieless_first', // or 'consent_required'
    // ...
],
```

**Available modes:**

| Mode | Behavior |
|------|----------|
| `cookieless_first` | Load immediately in cookieless mode (no cookies/localStorage). Upgrade to full tracking after consent. Best for maximum data collection while respecting privacy. |
| `consent_required` | Only load after user grants analytics consent (like GA4). No tracking at all until consent. Best for strict privacy compliance. |

```env
# Set via environment variable
CMP_WIREBOARD_LOADING_MODE=cookieless_first
```

---

## Custom CMP Options

The following options only apply when using the **Custom CMP** (AdSense disabled):

### Theme Customization

```php
'theme' => [
    'primary_bg' => '#1a73e8',        // Primary button background
    'primary_hover_bg' => '#1557b0',  // Primary button hover
    'primary_color' => '#ffffff',     // Primary button text
    'secondary_bg' => 'transparent',  // Secondary button background
    'secondary_border' => '#dadce0',  // Secondary button border
    'secondary_color' => '#1a73e8',   // Secondary button text
    'secondary_hover_bg' => '#f8f9fa',// Secondary button hover
    'modal_bg' => '#ffffff',          // Modal background
    'text_color' => '#202124',        // Main text color
    'border_radius' => '8px',         // Modal border radius
],
```

### GUI Options

```php
'custom_cmp' => [
    'gui_options' => [
        'consent_modal' => [
            'layout' => 'box inline',       // 'box', 'box inline', 'cloud', 'bar'
            'position' => 'middle center',  // Position on screen
            'equal_weight_buttons' => true, // Equal width buttons
            'flip_buttons' => true,         // [Manage] [Accept] order
        ],
        'preferences_modal' => [
            'layout' => 'box',
            'position' => 'middle center',
            'equal_weight_buttons' => true,
            'flip_buttons' => true,
        ],
    ],
],
```

### Reject All Button

Show or hide the "Reject all" button on both modals:

```php
'custom_cmp' => [
    'show_reject_button' => true,  // Set to false to hide
],
```

### Floating Settings Button

After the user makes their consent choice, a floating cookie icon appears allowing them to reopen their preferences:

```php
'custom_cmp' => [
    'show_settings_button' => true,       // Show/hide the floating button
    'settings_button_position' => 'bottom_left', // 'bottom_left' or 'bottom_right'
],
```

### Legal Links

Add privacy policy and terms of service links to the consent modal footer:

```php
'custom_cmp' => [
    'privacy_policy_url' => '/privacy-policy',
    'terms_url' => '/terms-of-service',
],
```

Links are automatically translated to the user's language.

### Cookie Categories

The package comes with two default categories: `necessary` and `analytics`. You can customize these and add new ones.

```php
'custom_cmp' => [
    'categories' => [
        'necessary' => [
            'enabled' => true,
            'read_only' => true,  // Cannot be disabled by user
        ],
        'analytics' => [
            'enabled' => true,    // Pre-toggled ON
            'read_only' => false,
            'auto_clear' => [     // Cookies to clear when disabled
                '/^_ga/',
                '_gid',
            ],
        ],
    ],
],
```

### Adding a Marketing Category

To add a "Marketing" category for ads, pixels, and tracking scripts:

**Step 1: Add the category in `config/cmp.php`:**

```php
'categories' => [
    'necessary' => [
        'enabled' => true,
        'read_only' => true,
    ],
    'analytics' => [
        'enabled' => true,
        'read_only' => false,
        'auto_clear' => ['/^_ga/', '_gid'],
    ],
    // Add marketing category
    'marketing' => [
        'enabled' => false,           // Pre-toggled OFF (user must opt-in)
        'read_only' => false,
        'auto_clear' => ['/^_fbp/', '/^_gcl/', '/^_fbc/'],  // Facebook, Google Ads cookies
    ],
],
```

**Step 2: Add translations for the new category.**

Publish translations if you haven't already:
```bash
php artisan vendor:publish --tag=cmp-translations
```

Edit `public/vendor/cmp/translations/en.json` and add a section for marketing:

```json
{
    "consentModal": {
        "title": "We use cookies",
        "description": "We use cookies and similar technologies to improve your experience and analyze traffic.",
        "acceptAllBtn": "Accept all",
        "acceptNecessaryBtn": "Reject all",
        "showPreferencesBtn": "Manage preferences"
    },
    "preferencesModal": {
        "title": "Cookie preferences",
        "acceptAllBtn": "Accept all",
        "acceptNecessaryBtn": "Reject all",
        "savePreferencesBtn": "Save preferences",
        "closeIconLabel": "Close",
        "sections": [
            {
                "title": "Necessary cookies",
                "description": "These cookies are essential for the website to function properly.",
                "linkedCategory": "necessary"
            },
            {
                "title": "Analytics cookies",
                "description": "These cookies help us understand how visitors interact with our website.",
                "linkedCategory": "analytics"
            },
            {
                "title": "Marketing cookies",
                "description": "These cookies are used to deliver personalized ads and track ad performance across websites.",
                "linkedCategory": "marketing"
            }
        ]
    }
}
```

> **Note:** Repeat this for each language file (fr.json, de.json, etc.) you want to support.

**Step 3: Load marketing scripts only after consent:**

```blade
<x-cmp::on-consent category="marketing">
    <!-- Facebook Pixel -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', 'YOUR_PIXEL_ID');
        fbq('track', 'PageView');
    </script>
</x-cmp::on-consent>
```

The `marketing` category automatically maps to `ad_storage` in Google Consent Mode v2.

### Adding Languages

1. Publish translations: `php artisan vendor:publish --tag=cmp-translations`
2. Create a new JSON file in `public/vendor/cmp/translations/` (e.g., `ja.json`)
3. Add the language code to `supported_languages` in config:

```php
'supported_languages' => ['en', 'fr', 'de', 'ja'], // Added Japanese
```

## How It Works

This package supports **two CMP modes** that are automatically selected based on your configuration:

### Mode 1: Google Funding Choices (AdSense Sites)

When `CMP_ADSENSE_ENABLED=true`, the package uses **Google Funding Choices** as the CMP.

```env
CMP_ADSENSE_ENABLED=true
CMP_ADSENSE_PUB_ID=pub-XXXXXXXXXX
```

**How it works:**
- Loads Google's official CMP script from `fundingchoicesmessages.google.com`
- Fully managed by Google - design and behavior configured in your [Google AdSense Funding Choices settings](https://fundingchoices.google.com/)
- Required for sites running Google AdSense ads
- Supports TCF 2.0 / IAB framework
- Consent choices sync across Google's ad network
- **Automatic fallback**: In regions where Google doesn't show consent banners (Brazil, South Africa, Thailand, etc.), the package automatically falls back to the Custom CMP to ensure users can still grant consent

**Best for:** Sites monetized with Google AdSense that need Google's certified CMP.

#### Required: Google AdSense Configuration

For Google Funding Choices to work properly with GA4 and other analytics, you **must enable Consent Mode** in your AdSense account:

1. Go to **Google AdSense** → **Privacy & messaging** → **European regulation** (or GDPR)
2. Enable the following options:
   - **Enable consent mode for advertising purposes**
   - **Enable consent mode for analytics purposes**
3. Save your settings

Without this configuration, the consent signals won't be passed to Google Analytics, and GA4 will never load even after the user grants consent.

> **Note:** You may also need to configure consent messages for other regions (California/CCPA, etc.) depending on your audience.

#### Automatic Fallback for Unsupported Regions

Google Funding Choices only shows consent banners in specific regions (EU, UK, California, etc.). For users in other regions like Brazil (LGPD), South Africa (POPIA), or Thailand (PDPA), Google returns a "not applicable" status and doesn't show any banner.

This package automatically detects this situation and falls back to the Custom CMP (vanilla-cookieconsent) to show a consent banner. This ensures:
- Users in all regions can grant or deny consent
- Your site remains compliant with privacy regulations worldwide
- GA4 and other analytics can load after consent is granted

This fallback is completely automatic - no additional configuration required.

### Mode 2: Custom CMP (vanilla-cookieconsent)

When `CMP_ADSENSE_ENABLED=false` (default), the package uses **vanilla-cookieconsent** as a fully customizable CMP.

```env
CMP_ADSENSE_ENABLED=false
```

**How it works:**
- Uses the open-source [vanilla-cookieconsent](https://github.com/orestbida/cookieconsent) library
- Fully customizable design via `config/cmp.php` and publishable assets
- Google-like appearance out of the box
- 13 languages included
- Features: Reject All button, floating settings button, legal links, custom categories

**Best for:** Sites without AdSense that want full control over their consent UI.

### Feature Comparison

| Feature | Google Funding Choices | Custom CMP |
|---------|----------------------|------------|
| Configuration | Google AdSense dashboard | `config/cmp.php` |
| Customizable design | Limited | Full control |
| Languages | Google managed | 13 included + custom |
| Reject All button | Google managed | Configurable |
| Floating settings button | No | Yes |
| Legal links (Privacy/Terms) | No | Yes |
| AdSense compatible | Required | Not needed |
| TCF 2.0 | Yes | No (Consent Mode v2) |
| Regional coverage | EU, UK, California only | Worldwide |
| Automatic fallback | Falls back to Custom CMP | N/A |

### Consent Flow

```
+------------------------------------------------------------------+
|                           PAGE LOAD                              |
+------------------------------------------------------------------+
                               |
                               v
+------------------------------------------------------------------+
|  1. CONSENT MODE DEFAULTS                                        |
|     gtag('consent', 'default', { analytics_storage: 'denied' })  |
+------------------------------------------------------------------+
                               |
                               v
                 +-----------------------------+
                 |   CMP_ADSENSE_ENABLED ?     |
                 +-----------------------------+
                        |             |
                   YES  |             |  NO
                        v             v
          +------------------+  +------------------+
          | Google Funding   |  | Custom CMP       |
          | Choices loads    |  | (cookieconsent)  |
          +------------------+  +------------------+
                        |             |
                        +------+------+
                               |
                               v
+------------------------------------------------------------------+
|  2. CONSENT MODAL SHOWN (first visit)                            |
|     User sees: [Reject All] [Manage Preferences] [Accept All]    |
+------------------------------------------------------------------+
                               |
                               v
+------------------------------------------------------------------+
|  3. USER MAKES CHOICE                                            |
+------------------------------------------------------------------+
                               |
                               v
+------------------------------------------------------------------+
|  4. CONSENT UPDATE                                               |
|     - gtag('consent', 'update', {...})                           |
|     - window.dispatchEvent('consent.update')                     |
+------------------------------------------------------------------+
                               |
                               v
                 +-----------------------------+
                 | analytics_storage granted?  |
                 +-----------------------------+
                        |             |
                   YES  |             |  NO
                        v             v
          +------------------+  +------------------+
          | 5a. GA4 LOADS    |  | 5b. GA4 blocked  |
          | Full tracking    |  | No tracking      |
          +------------------+  +------------------+
                               |
                               v
+------------------------------------------------------------------+
|  6. WIREBOARD (if enabled)                                       |
|     Runs in cookieless mode, can upgrade if consent granted      |
+------------------------------------------------------------------+
```

### About GA4 and Consent

This package loads GA4 only after explicit user consent because:
- GA4 sets cookies and transfers data to Google's servers
- Some EU Data Protection Authorities have raised concerns about Google Analytics
- Loading GA4 after consent provides a safer approach

### About WireBoard Analytics

[WireBoard](https://wireboard.io) is a privacy-focused analytics platform designed for modern web applications. Unlike traditional analytics tools, WireBoard offers:

- **Cookieless mode** - Can run without cookies or local storage for basic analytics
- **Privacy-first architecture** - Designed with privacy regulations in mind
- **Real-time analytics** - See your data as it happens
- **Lightweight** - Minimal impact on page performance

This package supports two loading modes for WireBoard:

**`cookieless_first` (default):**
- Loads immediately in cookieless mode (`useCookies: false`, `useLocalStorage: false`)
- Upgrades to full tracking after user grants consent
- Best for maximum data collection while respecting privacy

**`consent_required`:**
- Only loads after user grants analytics consent
- Full tracking enabled from the start (with consent)
- Best for strict privacy compliance (like GA4)

Learn more at [wireboard.io](https://wireboard.io).

> **Note:** Whether any analytics tool requires consent depends on your specific use case, jurisdiction, and legal interpretation. Consult with a legal professional.

## JavaScript Events

Listen for consent changes in your JavaScript:

```javascript
window.addEventListener('consent.update', function(e) {
    console.log('Consent state:', e.detail);
    // e.detail = {
    //   ad_storage: 'denied',
    //   analytics_storage: 'granted',
    //   ad_user_data: 'denied',
    //   ad_personalization: 'denied'
    // }
});
```

## Testing

### Check Which CMP Mode is Active

```javascript
console.log('CMP type:', window.__cmpType);  // 'google' or 'custom'
```

### Testing Custom CMP (vanilla-cookieconsent)

```javascript
// Check consent state
console.log('Consent state:', window.__consentState);
console.log('GA4 loaded:', window.__ga4Loaded);

// Reset consent to see modal again
CookieConsent.reset(true);
location.reload();

// Open preferences modal
CookieConsent.showPreferences();
```

### Testing Google Funding Choices

```javascript
// Check if Google FC is loaded
console.log('Google FC:', typeof window.googlefc);

// Check consent values
if (window.googlefc && window.googlefc.getGoogleConsentModeValues) {
    console.log('Consent:', window.googlefc.getGoogleConsentModeValues());
}

// Note: Google FC consent reset must be done through browser cookie clearing
// or the Google-provided UI
```

### Testing Regional Fallback

To test the automatic fallback to Custom CMP (for regions where Google doesn't show banners):

1. Use a VPN to connect from Brazil, South Africa, Thailand, or another unsupported region
2. Visit your site - you should see the Custom CMP banner instead of Google's
3. Check the console for CMP type:

```javascript
// Should show 'custom' when fallback is active
console.log('CMP type:', window.__cmpType);

// Check if fallback was triggered
console.log('Custom CMP initialized:', window.__customCMPInitialized);
```

**Expected behavior by region:**

| Region | CMP Shown |
|--------|-----------|
| EU/UK | Google Funding Choices |
| California | Google Funding Choices |
| Brazil | Custom CMP (fallback) |
| South Africa | Custom CMP (fallback) |
| Other regions | Custom CMP (fallback) |

### Verify Analytics Loading

```javascript
// Check if GA4 loaded after consent
console.log('GA4 loaded:', window.__ga4Loaded);
console.log('Has _ga cookie:', document.cookie.includes('_ga'));
```

## Building Assets (Development)

If you need to rebuild the minified assets:

```bash
cd vendor/wireboard/laravel-cmp

# Install dependencies
npm install

# Build for production (minified)
npm run build

# Build for development (not minified)
npm run dev

# Watch for changes
npm run watch
```

## Facade

You can also use the facade in your code:

```php
use Wireboard\Cmp\Facades\Cmp;

if (Cmp::isEnabled()) {
    // CMP is active
}

if (Cmp::isGa4Enabled()) {
    $measurementId = Cmp::getGa4MeasurementId();
}

$config = Cmp::getConfig();
```

## Version

v1.3.0

## Credits

This package includes the following open-source software:

- **[vanilla-cookieconsent](https://github.com/orestbida/cookieconsent)** by Orest Bida - MIT License

## License

MIT License - see [LICENSE](LICENSE) for full details including third-party licenses.

This is open-source software. You are free to use, modify, and distribute it under the terms of the MIT License.
