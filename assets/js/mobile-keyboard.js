(function () {
    'use strict';

    const fieldSelector = [
        // Semua input yang terlihat ikut ditangani, termasuk date, search, file,
        // checkbox, dan radio. Input hidden dikecualikan karena tidak interaktif.
        'input:not([type="hidden"])',
        'textarea',
        'select',
        '[contenteditable="true"]'
    ].join(',');
    const root = document.documentElement;
    const viewport = window.visualViewport;
    let activeField = null;
    let scrollTimer = 0;

    function isPhoneLayout() {
        return window.matchMedia('(max-width: 767.98px)').matches
            || window.matchMedia('(pointer: coarse)').matches;
    }

    function keyboardIsOpen() {
        if (!viewport || !isPhoneLayout()) return false;
        return window.innerHeight - viewport.height > 120;
    }

    function updateViewportState() {
        const visibleHeight = viewport ? viewport.height : window.innerHeight;
        const viewportTop = viewport ? viewport.offsetTop : 0;

        root.style.setProperty('--mobile-visible-height', `${Math.round(visibleHeight)}px`);
        root.style.setProperty('--mobile-viewport-top', `${Math.round(viewportTop)}px`);
        document.body.classList.toggle('mobile-keyboard-open', keyboardIsOpen());
    }

    function keepFieldVisible(field, delay) {
        window.clearTimeout(scrollTimer);
        scrollTimer = window.setTimeout(() => {
            if (!field?.isConnected || document.activeElement !== field || !isPhoneLayout()) return;

            const visibleHeight = viewport ? viewport.height : window.innerHeight;
            const viewportTop = viewport ? viewport.offsetTop : 0;
            const fieldRect = field.getBoundingClientRect();
            const safeTop = viewportTop + 72;
            const safeBottom = viewportTop + visibleHeight - 24;

            // Pindahkan hanya ketika kolom terpotong oleh navbar atau keyboard.
            if (fieldRect.top < safeTop || fieldRect.bottom > safeBottom) {
                field.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                    inline: 'nearest'
                });
            }
        }, delay);
    }

    document.addEventListener('focusin', event => {
        const field = event.target.closest?.(fieldSelector);
        if (!field || !isPhoneLayout()) return;
        activeField = field;
        updateViewportState();
        keepFieldVisible(field, 280);
    });

    document.addEventListener('focusout', event => {
        if (event.target !== activeField) return;
        activeField = null;
        window.setTimeout(updateViewportState, 180);
    });

    function handleViewportChange() {
        updateViewportState();
        if (activeField) keepFieldVisible(activeField, 80);
    }

    viewport?.addEventListener('resize', handleViewportChange);
    viewport?.addEventListener('scroll', handleViewportChange);
    window.addEventListener('resize', handleViewportChange);
    window.addEventListener('orientationchange', () => {
        window.setTimeout(handleViewportChange, 200);
    });

    updateViewportState();
}());
