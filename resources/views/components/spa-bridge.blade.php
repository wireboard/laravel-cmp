{{-- SPA navigation bridge: client-side navigations become page views. --}}
<script>
(function () {
    if (window.__cmpSpaBridge) {
        return;
    }
    window.__cmpSpaBridge = true;

    var hashRouting = @json($hashRouting);
    var titleWait = @json($titleWait);
    var queued = null;
    var settle = null;

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
     * Dispatch the page view. The address is the one seen when the navigation
     * was announced, so a report that goes out late still names its own page.
     * The title is read here, not then, because frameworks set it while the
     * new page mounts. `previousTitle` is the title the page being left had
     * at that moment, for a tag that wants to report that page after the
     * fact.
     */
    function dispatch(url, previous, previousTitle) {
        window.dispatchEvent(new CustomEvent('cmp:pageview', {
            detail: {
                url: url,
                referrer: previous,
                title: document.title,
                previousTitle: previousTitle,
            },
        }));
    }

    /**
     * Wait for the document title to settle before reporting, so a page view
     * never carries the previous page's title. `before` is the title seen when
     * the navigation was announced: the wait ends once the title differs from
     * it, or after `titleWait` for a page that keeps the same title.
     *
     * Only the title itself ends the wait. Anything else that touches the head
     * (a stylesheet or preload link for a lazily loaded chunk, a tag injected
     * by a script, a framework dropping the old page's meta tags before the
     * title arrives) is noise, and used to end the wait too early, with the
     * old title still in place.
     *
     * Returns a function that ends the wait at once, for the caller to use
     * when the next navigation arrives before this one was reported.
     */
    function whenTitleSettles(before, done) {
        var head = document.querySelector('head');
        var settled = false;
        var observer = null;
        var timer = null;

        function finish() {
            if (settled) {
                return;
            }
            settled = true;
            if (observer) {
                observer.disconnect();
            }
            window.clearTimeout(timer);
            done();
        }

        // Nothing to wait for: the title already moved since the announce (a
        // router that sets it right after pushState, seen on the History
        // patch, where the announce is synchronous), or waiting is off.
        if (titleWait <= 0 || typeof MutationObserver !== 'function' || !head || document.title !== before) {
            timer = window.setTimeout(finish, 0);

            return finish;
        }

        // Frameworks replace the <title> node as often as they edit it, so
        // watch the whole head rather than one element that may be gone, and
        // judge by the outcome rather than by which node moved.
        observer = new MutationObserver(function () {
            if (document.title !== before) {
                // One more frame: the title may be set in several passes.
                window.setTimeout(finish, 0);
            }
        });

        observer.observe(head, { childList: true, subtree: true, characterData: true });

        timer = window.setTimeout(finish, titleWait);

        return finish;
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
        var titleBefore = document.title;
        lastUrl = url;
        lastKey = key;

        // Anything still queued from this same tick describes this same move
        // (a router correcting the address it just pushed). That address was
        // never a page, so the referrer and the starting title stay those of
        // the page before it.
        if (queued) {
            window.clearTimeout(queued.timer);
            previous = queued.previous;
            titleBefore = queued.titleBefore;
        }

        // A report still waiting on the previous page's title goes out now,
        // with that page's address and the title as it stands. Left waiting,
        // it would see this page's title land and report this page twice,
        // and the previous one never.
        if (settle) {
            settle();
        }

        queued = {
            previous: previous,
            titleBefore: titleBefore,
            timer: window.setTimeout(function () {
                queued = null;
                settle = whenTitleSettles(titleBefore, function () {
                    settle = null;
                    dispatch(url, previous, titleBefore);
                });
            }, 0),
        };
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
