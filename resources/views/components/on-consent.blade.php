{{-- On-Consent Component: Loads content only after consent is granted --}}
<template id="{{ $uniqueId }}">
{{ $slot }}
</template>
<script>
(function() {
    var templateId = '{{ $uniqueId }}';
    var consentType = '{{ $consentType }}';
    var loaded = false;

    function loadContent() {
        if (loaded) return;
        loaded = true;

        var template = document.getElementById(templateId);
        if (!template) return;

        // Get the template content
        var content = template.content.cloneNode(true);

        // Handle script tags specially - they need to be recreated to execute
        var scripts = content.querySelectorAll('script');
        var nonScriptContent = [];

        // Collect non-script nodes
        content.childNodes.forEach(function(node) {
            if (node.nodeName !== 'SCRIPT') {
                nonScriptContent.push(node.cloneNode(true));
            }
        });

        // Append non-script content first
        nonScriptContent.forEach(function(node) {
            template.parentNode.insertBefore(node, template);
        });

        // Recreate and execute scripts
        scripts.forEach(function(oldScript) {
            var newScript = document.createElement('script');

            // Copy all attributes
            Array.from(oldScript.attributes).forEach(function(attr) {
                newScript.setAttribute(attr.name, attr.value);
            });

            // Copy inline script content
            if (oldScript.textContent) {
                newScript.textContent = oldScript.textContent;
            }

            // Append to head (for external scripts) or body (for inline)
            if (oldScript.src) {
                document.head.appendChild(newScript);
            } else {
                document.body.appendChild(newScript);
            }
        });

        // Remove the template
        template.remove();
    }

    // Check if consent is already granted
    if (window.__consentState && window.__consentState[consentType] === 'granted') {
        loadContent();
        return;
    }

    // Listen for consent updates
    window.addEventListener('consent.update', function(e) {
        if (e.detail && e.detail[consentType] === 'granted') {
            loadContent();
        }
    });
})();
</script>
