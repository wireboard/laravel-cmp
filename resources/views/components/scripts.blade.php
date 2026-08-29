{{-- All CMP Consent Scripts in correct order --}}
{{-- Usage: <x-cmp::scripts /> --}}

{{-- 1. Consent Mode v2 Defaults - MUST be first --}}
<x-cmp-consent-mode />

{{-- 2. CMP Script (Google Funding Choices or vanilla-cookieconsent) --}}
<x-cmp-script />

{{-- 3. Consent State Tracker --}}
<x-cmp-consent-tracker />

{{-- 4. Google Analytics 4 (consent-gated) --}}
<x-cmp-google-analytics />

{{-- 5. WireBoard Analytics (legitimate interest, cookieless by default) --}}
<x-cmp-wireboard />

{{-- 6. SPA bridge - turns client-side navigations into page views. Last, so
       the trackers above have already registered their listeners. --}}
<x-cmp-spa-bridge />
