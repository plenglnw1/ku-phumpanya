

import Alpine from 'alpinejs';
import './knowledge-graph.js';

window.Alpine = Alpine;

Alpine.start();

document.querySelectorAll('[data-search-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-search-submit]');
        if (!button || button.disabled) {
            return;
        }

        button.disabled = true;
        button.textContent = 'Searching…';
    });
});
