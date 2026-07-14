import Alpine from '@alpinejs/csp';

window.Alpine = Alpine;

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.uploadApp === 'function') {
        Alpine.data('uploadApp', window.uploadApp);
    }

    Alpine.start();
}, { once: true });
