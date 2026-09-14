/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// start the Stimulus application
import './bootstrap';

const menuButton = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');

menuButton?.addEventListener('click', () => {
    const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
    menuButton.setAttribute('aria-expanded', String(!isExpanded));
    menu?.classList.toggle('hidden');
});

document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => {
        document.getElementById(button.dataset.dialogOpen)?.showModal();
    });
});

document.querySelectorAll('[data-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
});

document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
});

const lightbox = document.getElementById('image-lightbox');
const lightboxImage = lightbox?.querySelector('img');

document.querySelectorAll('[data-lightbox]').forEach((link) => {
    link.addEventListener('click', (event) => {
        event.preventDefault();
        if (lightboxImage) {
            lightboxImage.src = link.href;
            lightboxImage.alt = link.querySelector('img')?.alt || 'Composition florale agrandie';
        }
        lightbox?.showModal();

        if (link.dataset.mediaViewUrl) {
            fetch(link.dataset.mediaViewUrl, {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            })
                .then((response) => response.ok ? response.json() : null)
                .then((data) => {
                    const count = document.getElementById(link.dataset.mediaViewCount);
                    if (count && data) count.textContent = data.viewCount;
                })
                .catch(() => {});
        }
    });
});
