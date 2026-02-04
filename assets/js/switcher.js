/**
 * Translio Language Switcher Frontend JS
 */
(function() {
    'use strict';

    function initSwitcher() {
        var switchers = document.querySelectorAll('.translio-switcher--dropdown');

        switchers.forEach(function(switcher) {
            var toggle = switcher.querySelector('.translio-switcher__toggle');
            var dropdown = switcher.querySelector('.translio-switcher__dropdown');

            if (!toggle || !dropdown) return;

            // Toggle dropdown on click
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var isOpen = toggle.getAttribute('aria-expanded') === 'true';

                // Close other open dropdowns
                closeAllDropdowns();

                if (!isOpen) {
                    toggle.setAttribute('aria-expanded', 'true');
                    switcher.classList.add('translio-switcher--open');
                }
            });

            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!switcher.contains(e.target)) {
                    toggle.setAttribute('aria-expanded', 'false');
                    switcher.classList.remove('translio-switcher--open');
                }
            });

            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    toggle.setAttribute('aria-expanded', 'false');
                    switcher.classList.remove('translio-switcher--open');
                }
            });
        });
    }

    function closeAllDropdowns() {
        var toggles = document.querySelectorAll('.translio-switcher__toggle');
        toggles.forEach(function(t) {
            t.setAttribute('aria-expanded', 'false');
            t.closest('.translio-switcher').classList.remove('translio-switcher--open');
        });
    }

    // Init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSwitcher);
    } else {
        initSwitcher();
    }
})();
