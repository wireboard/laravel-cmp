<?php

namespace Wireboard\Cmp\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Wireboard\Cmp\Facades\Cmp;

/**
 * Cookie Preferences Link Component
 *
 * Renders a link that opens the consent preferences modal.
 * Adapts to the CMP type (Google Funding Choices or vanilla-cookieconsent).
 */
class CookiePreferencesLink extends Component
{
    public bool $enabled;
    public string $cmpType;
    public string $text;
    public string $class;

    public function __construct(
        ?string $text = null,
        string $class = ''
    ) {
        $this->enabled = Cmp::isEnabled();
        $this->cmpType = Cmp::getCmpType();
        $this->text = $text ?? __('Manage Cookie Preferences');
        $this->class = $class;
    }

    public function render(): View|string
    {
        if (! $this->enabled) {
            return '';
        }

        return view('cmp::components.cookie-preferences-link');
    }
}
