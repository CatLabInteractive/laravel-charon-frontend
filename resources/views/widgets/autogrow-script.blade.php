{{-- Textareas with data-charon-autogrow grow with their text instead of
     scrolling inside a small box. The rows attribute stays the minimum height;
     the editor can still drag the corner. Included once by every form and by
     the textarea widget. Call CharonFrontend.autogrow(textarea) after setting
     a value from script (that fires no input event). --}}
@once
<script>
(function () {
    var SELECTOR = 'textarea[data-charon-autogrow]';
    var api = window.CharonFrontend = window.CharonFrontend || {};
    if (api.autogrow) {
        return;
    }

    var visibility = 'IntersectionObserver' in window
        ? new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    grow(entry.target);
                }
            });
        })
        : null;

    function isHidden(textarea) {
        return textarea.offsetWidth === 0 && textarea.offsetHeight === 0;
    }

    function grow(textarea) {
        if (isHidden(textarea)) {
            // In a closed tab or <details>: sized when it shows up.
            return;
        }

        var style = window.getComputedStyle(textarea);
        if (!textarea.hasAttribute('data-charon-autogrow-min')) {
            // The height the rows attribute gives is the minimum.
            textarea.setAttribute('data-charon-autogrow-min', textarea.offsetHeight);
            textarea.style.overflowY = 'hidden';
        }

        var min = parseFloat(textarea.getAttribute('data-charon-autogrow-min')) || 0;
        var extra = style.boxSizing === 'border-box'
            ? parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth)
            : -(parseFloat(style.paddingTop) + parseFloat(style.paddingBottom));

        // Shrinking first can shorten the page for a moment; keep the scroll.
        var scrollX = window.scrollX;
        var scrollY = window.scrollY;
        textarea.style.height = 'auto';
        textarea.style.height = Math.max(min, textarea.scrollHeight + extra) + 'px';
        if (window.scrollY !== scrollY) {
            window.scrollTo(scrollX, scrollY);
        }
    }

    function watch(textarea) {
        if (textarea.hasAttribute('data-charon-autogrow-ready')) {
            return;
        }
        textarea.setAttribute('data-charon-autogrow-ready', '1');
        if (visibility) {
            visibility.observe(textarea);
        }
        grow(textarea);
    }

    function scan(root) {
        if (root.matches && root.matches(SELECTOR)) {
            watch(root);
        }
        if (root.querySelectorAll) {
            Array.prototype.forEach.call(root.querySelectorAll(SELECTOR), watch);
        }
    }

    api.autogrow = function (target) {
        if (target && target.matches && target.matches(SELECTOR)) {
            grow(target);
        } else {
            scan(target || document);
        }
    };

    document.addEventListener('input', function (event) {
        if (event.target.matches && event.target.matches(SELECTOR)) {
            grow(event.target);
        }
    });

    // A narrower window wraps more lines.
    var resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            Array.prototype.forEach.call(document.querySelectorAll(SELECTOR), grow);
        }, 100);
    });

    function start() {
        scan(document);

        // Fields added later (repeaters, blocks, templates).
        if ('MutationObserver' in window) {
            new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    Array.prototype.forEach.call(mutation.addedNodes, function (node) {
                        if (node.nodeType === 1) {
                            scan(node);
                        }
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
@endonce
