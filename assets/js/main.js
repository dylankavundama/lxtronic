/**
 * Lxtronic - Main JS
 * Modern Navigation Loader & Global Interactivity
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Loader Element
    const loader = document.createElement('div');
    loader.id = 'nav-loader';
    document.body.appendChild(loader);

    /**
     * Start the loader animation
     */
    const startLoader = () => {
        loader.classList.remove('finished');
        loader.classList.add('loading');
        loader.style.width = '0%';

        // Fake progress
        setTimeout(() => {
            if (loader.classList.contains('loading')) {
                loader.style.width = '30%';
            }
        }, 50);

        setTimeout(() => {
            if (loader.classList.contains('loading')) {
                loader.style.width = '70%';
            }
        }, 400);

        setTimeout(() => {
            if (loader.classList.contains('loading')) {
                loader.style.width = '90%';
            }
        }, 1000);
    };

    /**
     * Finish and hide the loader
     */
    const finishLoader = () => {
        loader.style.width = '100%';
        loader.classList.add('finished');
        loader.classList.remove('loading');

        setTimeout(() => {
            loader.style.width = '0%';
            loader.classList.remove('finished');
        }, 400);
    };

    // 2. Intercept Link Clicks
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a');

        if (link &&
            link.href &&
            !link.target &&
            !link.hasAttribute('download') &&
            link.href.startsWith(window.location.origin) &&
            !link.href.includes('#') &&
            link.href !== window.location.href + '#' &&
            !e.ctrlKey && !e.shiftKey && !e.metaKey && !e.altKey) {

            // Only trigger for pages, not for same-page anchors or javascript:void(0)
            if (link.hostname === window.location.hostname &&
                !link.href.startsWith('javascript:') &&
                !link.href.startsWith('mailto:') &&
                !link.href.startsWith('tel:')) {

                startLoader();
            }
        }
    });

    // 3. Handle Page Unload (redundancy for forms or other navigations)
    window.addEventListener('beforeunload', () => {
        startLoader();
    });

    // 4. Initial Finish (for the current page load)
    // The loader starts hidden, so we only finish if it was somehow shown
    // But since we inject it on DOMContentLoaded, it might be already loading if the browser started fetching resources
    finishLoader();
});
