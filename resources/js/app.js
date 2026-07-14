import Alpine from '@alpinejs/csp';

window.Alpine = Alpine;

document.addEventListener('DOMContentLoaded', () => {
    Alpine.start();
}, { once: true });
