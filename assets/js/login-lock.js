(() => {
    const alertElement = document.querySelector('[data-login-lock]');
    const form = document.querySelector('[data-login-lock-form]');
    if (!alertElement || !form) return;

    const countdown = alertElement.querySelector('[data-login-countdown]');
    let remaining = Math.max(0, Number(alertElement.dataset.loginLock) || 0);
    let timer = null;

    const updateCountdown = () => {
        if (countdown) countdown.textContent = String(remaining);
        if (remaining > 0) return;

        if (timer !== null) window.clearInterval(timer);
        alertElement.classList.add('d-none');
        form.querySelectorAll('input, button').forEach(control => {
            control.disabled = false;
        });
        form.querySelector('input[name="username"]')?.focus();
    };

    updateCountdown();
    timer = window.setInterval(() => {
        remaining = Math.max(0, remaining - 1);
        updateCountdown();
    }, 1000);
})();
