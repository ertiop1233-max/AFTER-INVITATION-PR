import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });
});

window.Alpine = Alpine;
