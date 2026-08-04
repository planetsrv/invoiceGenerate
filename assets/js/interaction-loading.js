(function () {
    'use strict';

    let pendingRequests = 0;
    let hideTimer = 0;
    let safetyTimer = 0;

    const overlay = document.createElement('div');
    overlay.className = 'app-loading-overlay';
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = `
        <div class="app-loading-card">
            <span class="app-loading-spinner" aria-hidden="true"></span>
            <span class="app-loading-text">Memuat...</span>
        </div>`;
    document.body.appendChild(overlay);

    const loadingText = overlay.querySelector('.app-loading-text');

    function showLoading(message = 'Memuat...') {
        window.clearTimeout(hideTimer);
        window.clearTimeout(safetyTimer);
        loadingText.textContent = message;
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('app-is-loading');
        safetyTimer = window.setTimeout(() => hideLoading(true), 20000);
    }

    function hideLoading(force = false) {
        if (!force && pendingRequests > 0) return;
        window.clearTimeout(safetyTimer);
        hideTimer = window.setTimeout(() => {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('app-is-loading');
        }, 120);
    }

    function beginRequest(message) {
        pendingRequests++;
        showLoading(message);
    }

    function endRequest() {
        pendingRequests = Math.max(0, pendingRequests - 1);
        hideLoading();
    }

    window.appLoading = { show: showLoading, hide: () => hideLoading(true) };

    // Semua request fetch mendapat indikator secara otomatis.
    if (window.fetch) {
        const nativeFetch = window.fetch.bind(window);
        window.fetch = function (...args) {
            beginRequest('Memproses...');
            return nativeFetch(...args).finally(endRequest);
        };
    }

    // Semua XMLHttpRequest ikut ditangani, kecuali upload Excel yang memiliki progress sendiri.
    const nativeXhrSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function (body) {
        const isVoucherUpload = body instanceof FormData && body.has('excelfile');
        if (!isVoucherUpload) {
            beginRequest('Memproses...');
            this.addEventListener('loadend', endRequest, { once: true });
        }
        return nativeXhrSend.call(this, body);
    };

    // Navigasi halaman dan download memperlihatkan respons visual segera setelah klik.
    document.addEventListener('click', event => {
        const button = event.target.closest('button, .btn');
        if (button && !button.disabled) {
            button.classList.add('is-interacting');
            window.setTimeout(() => button.classList.remove('is-interacting'), 180);
        }

        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented) return;
        const href = link.getAttribute('href') || '';
        if (!href || href.startsWith('#') || href.startsWith('javascript:')
            || href.startsWith('mailto:') || href.startsWith('tel:')
            || link.hasAttribute('data-bs-toggle')) return;

        showLoading(link.hasAttribute('download') ? 'Menyiapkan file...' : 'Membuka...');
        if (link.target === '_blank' || link.hasAttribute('download') || href.includes('action=')) {
            window.setTimeout(() => hideLoading(true), 1800);
        }
    });

    document.addEventListener('submit', event => {
        window.setTimeout(() => {
            if (!event.defaultPrevented && event.target?.target !== '_blank') {
                showLoading('Menyimpan...');
            }
        }, 0);
    });

    window.addEventListener('beforeunload', () => showLoading('Memuat halaman...'));
    window.addEventListener('pageshow', () => {
        pendingRequests = 0;
        hideLoading(true);
    });
}());
