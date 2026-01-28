<?php

namespace Wireboard\Cmp;

class Cmp
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Check if the CMP is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * Check if AdSense (Google Funding Choices) mode is enabled.
     */
    public function isAdSenseEnabled(): bool
    {
        return (bool) ($this->config['adsense']['enabled'] ?? false);
    }

    /**
     * Get the AdSense publisher ID.
     */
    public function getAdSensePubId(): ?string
    {
        return $this->config['adsense']['pub_id'] ?? null;
    }

    /**
     * Check if Google Analytics 4 is enabled.
     */
    public function isGa4Enabled(): bool
    {
        return (bool) ($this->config['google_analytics']['enabled'] ?? false);
    }

    /**
     * Get the GA4 measurement ID.
     */
    public function getGa4MeasurementId(): ?string
    {
        return $this->config['google_analytics']['measurement_id'] ?? null;
    }

    /**
     * Get the GA4 cookie flags.
     */
    public function getGa4CookieFlags(): string
    {
        return $this->config['google_analytics']['cookie_flags'] ?? 'SameSite=Lax';
    }

    /**
     * Check if WireBoard is enabled.
     */
    public function isWireBoardEnabled(): bool
    {
        return (bool) ($this->config['wireboard']['enabled'] ?? false);
    }

    /**
     * Get WireBoard configuration.
     */
    public function getWireBoardConfig(): array
    {
        return $this->config['wireboard'] ?? [];
    }

    /**
     * Get custom CMP configuration.
     */
    public function getCustomCmpConfig(): array
    {
        return $this->config['custom_cmp'] ?? [];
    }

    /**
     * Get theme configuration.
     */
    public function getThemeConfig(): array
    {
        return $this->config['theme'] ?? [];
    }

    /**
     * Get consent defaults configuration.
     */
    public function getConsentDefaults(): array
    {
        return $this->config['consent_defaults'] ?? [
            'ad_storage' => 'denied',
            'analytics_storage' => 'denied',
            'ad_user_data' => 'denied',
            'ad_personalization' => 'denied',
            'wait_for_update' => 500,
        ];
    }

    /**
     * Determine the CMP type.
     */
    public function getCmpType(): string
    {
        return $this->isAdSenseEnabled() ? 'google' : 'custom';
    }

    /**
     * Get all configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get a specific configuration value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Generate CSS variables from theme config.
     */
    public function getThemeCssVariables(): string
    {
        $theme = $this->getThemeConfig();

        return implode("\n", [
            '--cc-btn-primary-bg: ' . ($theme['primary_bg'] ?? '#1a73e8') . ';',
            '--cc-btn-primary-hover-bg: ' . ($theme['primary_hover_bg'] ?? '#1557b0') . ';',
            '--cc-btn-primary-color: ' . ($theme['primary_color'] ?? '#fff') . ';',
            '--cc-btn-secondary-bg: ' . ($theme['secondary_bg'] ?? 'transparent') . ';',
            '--cc-btn-secondary-border-color: ' . ($theme['secondary_border'] ?? '#dadce0') . ';',
            '--cc-btn-secondary-color: ' . ($theme['secondary_color'] ?? '#1a73e8') . ';',
            '--cc-btn-secondary-hover-bg: ' . ($theme['secondary_hover_bg'] ?? '#f8f9fa') . ';',
            '--cc-modal-border-radius: ' . ($theme['border_radius'] ?? '8px') . ';',
            '--cc-bg: ' . ($theme['modal_bg'] ?? '#fff') . ';',
            '--cc-text: ' . ($theme['text_color'] ?? '#202124') . ';',
            '--cc-separator-border-color: ' . ($theme['separator_color'] ?? '#e8eaed') . ';',
            '--cc-toggle-on-bg: ' . ($theme['toggle_on_bg'] ?? '#1a73e8') . ';',
        ]);
    }
}
