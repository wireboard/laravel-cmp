# WireBoard Laravel CMP

WireBoard.io consent management platform for Laravel - a GDPR/TCF 2.0 compliant CMP with support for WireBoard Analytics, Google Analytics 4, and Google Funding Choices.

## Features

- **GDPR Compliant** - Consent Mode v2 with all storage types denied by default
- **Flexible CMP Options** - Google Funding Choices (for AdSense sites) or vanilla-cookieconsent (custom CMP)
- **Google Analytics 4** - Consent-gated loading (only loads after user consent)
- **WireBoard Analytics** - Legitimate interest with cookieless mode by default
- **Fully Configurable** - Theme colors, button layouts, cookie categories, languages
- **13 Languages** - English, French, German, Spanish, Italian, Dutch, Portuguese, Polish, Danish, Swedish, Norwegian, Finnish, Hungarian
- **Publishable Assets** - Customize CSS, JS, translations, and views

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x

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

# WireBoard Analytics (legitimate interest, cookieless by default)
CMP_WIREBOARD_ENABLED=false
CMP_WIREBOARD_PIPELINE=pipeline-0.wireboard.io  # Default pipeline
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

### Cookie Preferences Link

Add a link to let users manage their cookie preferences (required for GDPR):

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

## Configuration Options

After publishing the config, edit `config/cmp.php`:

### Enable/Disable CMP

```php
'enabled' => env('CMP_ENABLED', true),
```

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

### Cookie Categories

```php
'custom_cmp' => [
    'categories' => [
        'necessary' => [
            'enabled' => true,
            'read_only' => true,  // Cannot be disabled
        ],
        'analytics' => [
            'enabled' => true,    // Pre-toggled ON
            'read_only' => false,
            'auto_clear' => [     // Cookies to clear when disabled
                '/^_ga/',
                '_gid',
            ],
        ],
        // Add marketing category:
        'marketing' => [
            'enabled' => false,
            'read_only' => false,
            'auto_clear' => ['/^_fbp/', '/^_gcl/'],
        ],
    ],
],
```

### Adding Languages

1. Publish translations: `php artisan vendor:publish --tag=cmp-translations`
2. Create a new JSON file in `public/vendor/cmp/translations/` (e.g., `ja.json`)
3. Add the language code to `supported_languages` in config:

```php
'supported_languages' => ['en', 'fr', 'de', 'ja'], // Added Japanese
```

## How It Works

### Consent Flow

1. **Consent Mode v2 Defaults** - All storage types set to `denied`
2. **CMP Loads** - Shows consent modal on first visit
3. **User Interacts** - Accepts or customizes preferences
4. **Consent Update** - Fires `consent.update` event
5. **Analytics Load** - GA4 loads only if `analytics_storage: granted`
6. **WireBoard** - Runs in cookieless mode, upgrades on consent

### Why GA4 Requires Consent

EU Data Protection Authorities ruled that Google Analytics violates GDPR even in cookieless mode because:
- Data transfers to Google's US servers (Schrems II violation)
- IP addresses reach Google before truncation
- Google is subject to US surveillance laws

### Why WireBoard Can Run Without Consent

WireBoard operates under Legitimate Interest because:
- `useCookies: false` - No cookies stored
- `useLocalStorage: false` - No local storage used
- EU data processing only - No US data transfer
- IP addresses not stored

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

Open the browser console and run:

```javascript
// Before consent:
console.log('CMP type:', window.__cmpType);
console.log('Consent state:', window.__consentState);
console.log('GA4 loaded:', window.__ga4Loaded);

// After accepting:
console.log('Consent state:', window.__consentState);
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

## Credits

This package includes the following open-source software:

- **[vanilla-cookieconsent](https://github.com/orestbida/cookieconsent)** by Orest Bida - MIT License

## License

MIT License - see [LICENSE](LICENSE) for full details including third-party licenses.

This is open-source software. You are free to use, modify, and distribute it under the terms of the MIT License.
