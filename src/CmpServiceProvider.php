<?php

namespace Wireboard\Cmp;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Wireboard\Cmp\View\Components\CmpScript;
use Wireboard\Cmp\View\Components\ConsentMode;
use Wireboard\Cmp\View\Components\ConsentTracker;
use Wireboard\Cmp\View\Components\CookiePreferencesLink;
use Wireboard\Cmp\View\Components\GoogleAnalytics;
use Wireboard\Cmp\View\Components\OnConsent;
use Wireboard\Cmp\View\Components\Scripts;
use Wireboard\Cmp\View\Components\SpaBridge;
use Wireboard\Cmp\View\Components\WireBoard;

class CmpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cmp.php',
            'cmp'
        );

        $this->app->singleton('wireboard-cmp', function ($app) {
            return new Cmp($app['config']->get('cmp'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerViews();
        $this->registerComponents();
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/cmp.php' => config_path('cmp.php'),
            ], 'cmp-config');

            // Publish assets (minified production files)
            $this->publishes([
                __DIR__ . '/../dist/js' => public_path('vendor/cmp/js'),
                __DIR__ . '/../dist/css' => public_path('vendor/cmp/css'),
            ], 'cmp-assets');

            // Publish translations
            $this->publishes([
                __DIR__ . '/../resources/translations' => public_path('vendor/cmp/translations'),
            ], 'cmp-translations');

            // Publish views (for customization)
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/cmp'),
            ], 'cmp-views');

            // Publish source assets (for custom builds)
            $this->publishes([
                __DIR__ . '/../resources/js/consent-cmp.js' => resource_path('vendor/cmp/js/consent-cmp.js'),
                __DIR__ . '/../resources/css' => resource_path('vendor/cmp/css'),
            ], 'cmp-source');

            // Publish React components
            $this->publishes([
                __DIR__ . '/../resources/js/react/CookiePreferencesLink.tsx' => resource_path('js/components/CookiePreferencesLink.tsx'),
            ], 'cmp-react');

            // Publish Vue components
            $this->publishes([
                __DIR__ . '/../resources/js/vue/CookiePreferencesLink.vue' => resource_path('js/components/CookiePreferencesLink.vue'),
                __DIR__ . '/../resources/js/vue/useCookieConsent.ts' => resource_path('js/composables/useCookieConsent.ts'),
            ], 'cmp-vue');

            // Publish everything
            $this->publishes([
                __DIR__ . '/../config/cmp.php' => config_path('cmp.php'),
                __DIR__ . '/../dist/js' => public_path('vendor/cmp/js'),
                __DIR__ . '/../dist/css' => public_path('vendor/cmp/css'),
                __DIR__ . '/../resources/translations' => public_path('vendor/cmp/translations'),
            ], 'cmp');
        }
    }

    /**
     * Register the package views.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cmp');
    }

    /**
     * Register the Blade components.
     */
    protected function registerComponents(): void
    {
        Blade::component('cmp-consent-mode', ConsentMode::class);
        Blade::component('cmp-script', CmpScript::class);
        Blade::component('cmp-consent-tracker', ConsentTracker::class);
        Blade::component('cmp-google-analytics', GoogleAnalytics::class);
        Blade::component('cmp-wireboard', WireBoard::class);
        Blade::component('cmp-cookie-preferences-link', CookiePreferencesLink::class);
        Blade::component('cmp-on-consent', OnConsent::class);
        Blade::component('cmp-scripts', Scripts::class);
        Blade::component('cmp-spa-bridge', SpaBridge::class);

        // Register components with cmp:: prefix for namespaced usage
        Blade::componentNamespace('Wireboard\\Cmp\\View\\Components', 'cmp');
    }
}
