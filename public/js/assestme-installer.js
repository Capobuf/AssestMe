document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-recheck-health]');

    if (!button) return;

    button.disabled = true;

    try {
        const response = await fetch('/up', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        const payload = await response.json();
        const status = document.querySelector('[data-scheduler-status]');

        if (status) {
            status.textContent = payload.scheduler === 'verified'
                ? 'Scheduler verificato.'
                : 'Scheduler non ancora verificato.';
        }
    } catch {
        const status = document.querySelector('[data-scheduler-status]');
        if (status) status.textContent = 'Verifica scheduler non riuscita.';
    } finally {
        button.disabled = false;
    }
});
