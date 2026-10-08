(() => {
    'use strict';
    const dialog = document.querySelector('[data-xd20-consultation]');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    let opener;
    document.querySelectorAll('[data-xd20-consultation-open]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (dialog.open) return;
            opener = button;
            dialog.showModal();
            document.body.classList.add('xd20-consultation-open');
            dialog.querySelector('input[name="name"]')?.focus();
        });
    });
    dialog.querySelector('[data-xd20-consultation-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => {
        document.body.classList.remove('xd20-consultation-open');
        opener?.focus();
    });
})();
