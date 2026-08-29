{{-- SPA navigation bridge: client-side navigations become page views. --}}
<script>
(function () {
    if (window.__cmpSpaBridge) {
        return;
    }
    window.__cmpSpaBridge = true;

    var hashRouting = @json($hashRouting);
    var titleWait = @json($titleWait);
    var pending = null;

    /**
     * The address a page view is keyed on. The fragment counts only for a hash
     * router. Elsewhere `#pricing` is a jump within the page rather than a new
     * one, and only Chromium's Navigation API calls it a navigation at all, so
     * counting it would report views that Firefox and Safari never send.
     */
    function navKey(href) {
        if (hashRouting) {
            return href;
        }

        var hash = href.indexOf('#');

        return hash === -1 ? href : href.slice(0, hash);
    }

    var lastUrl = window.location.href;
    var lastKey = navKey(lastUrl);

    /**
     * Dispatch the page view. The title is read here, not when the navigation
     * was detected, because frameworks set it while the new page mounts.
     */
    function dispatch(previous) {
        window.dispatchEvent(new CustomEvent('cmp:pageview', {
            detail: {
                url: window.location.href,
                referrer: previous,
                title: document.title,
            },
        }));
    }

    /**
     * Wait for the document title to settle before reporting, so a page view
     * never carries the previous page's title. Resolves on the first title
     * mutation, or after `titleWait` if the page kept the same title.
     */
    function whenTitleSettles(done) {
        var head = document.querySelector('head');

        if (titleWait <= 0 || typeof MutationObserver !== 'function' || !head) {
            window.setTimeout(done, 0);

            return;
        }

        var settled = false;

        function finish() {
            if (settled) {
                return;
            }
            settled = true;
            observer.disconnect();
            window.clearTimeout(timer);
            done();
        }

        // Frameworks replace the <title> node as often as they edit it, so
        // watch the whole head rather than one element that may be gone.
        var observer = new MutationObserver(function (records) {
            for (var i = 0; i < records.length; i++) {
                var target = records[i].target;

                if (target && (target.nodeName === 'TITLE' || target.nodeName === 'HEAD')) {
                    // One more frame: the title may be set in several passes.
                    window.setTimeout(finish, 0);

                    return;
                }
            }
        });

        observer.observe(head, { childList: true, subtree: true, characterData: true });

        var timer = window.setTimeout(finish, titleWait);
    }

    /**
     * Announce a navigation. `force` is for hosts calling this by hand after a
     * change that leaves the URL untouched (a tab, a modal route).
     */
    function announce(force) {
        var url = window.location.href;
        var key = navKey(url);

        // One navigation announces itself several times over: a framework
        // event, its own pushState, and the Navigation API. They all carry the
        // same address, so comparing against the last reported one collapses
        // them without a timer that would also swallow a genuine second
        // navigation.
        if (!force && key === lastKey) {
            return;
        }

        var previous = lastUrl;
        lastUrl = url;
        lastKey = key;

        // Anything still queued from this same tick describes this same move.
        if (pending) {
            window.clearTimeout(pending);
        }

        pending = window.setTimeout(function () {
            pending = null;
            whenTitleSettles(function () {
                dispatch(previous);
            });
        }, 0);
    }

    // Public hook, so a host can report a view its router never announced.
    window.cmpTrackPageView = function () {
        announce(true);
    };

    function onNavigation() {
        announce(false);
    }

    @foreach ($events as $event)
    document.addEventListener(@json($event), onNavigation);
    window.addEventListener(@json($event), onNavigation);
    @endforeach

    @if ($watchHistory)
    if (window.navigation && typeof window.navigation.addEventListener === 'function') {
        // Navigation API (Chromium): a first-class signal, no global patching.
        window.navigation.addEventListener('navigatesuccess', onNavigation);
    } else {
        // Fallback for browsers without it. Routers that announce nothing of
        // their own still go through the History API; wrapping keeps the
        // original behaviour and only adds a callback.
        ['pushState', 'replaceState'].forEach(function (method) {
            var original = window.history[method];

            if (typeof original !== 'function') {
                return;
            }

            window.history[method] = function () {
                var result = original.apply(this, arguments);
                onNavigation();

                return result;
            };
        });
    }

    window.addEventListener('popstate', onNavigation);
    @endif
})();
</script>
