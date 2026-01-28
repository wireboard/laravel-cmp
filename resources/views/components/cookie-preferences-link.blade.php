{{-- Cookie Preferences Link - opens consent modal --}}
@if($cmpType === 'google')
<a href="#" @class([$class]) onclick="__tcfapi('displayConsentUi', 2, function() {}); return false;">
    {{ $text }}
</a>
@else
<a href="#" @class([$class]) onclick="CookieConsent.showPreferences(); return false;">
    {{ $text }}
</a>
@endif
