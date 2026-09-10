(() => {
    const buttons = Array.from(document.querySelectorAll('.image-preview-button, .image-grid:not(#apartmentGallery) .image-item img'));
    if (!buttons.length) return;

    const lightbox = document.createElement('div');
    lightbox.className = 'image-lightbox';
    lightbox.id = 'imageLightbox';
    lightbox.setAttribute('aria-hidden', 'true');
    lightbox.innerHTML = '<div class="lightbox-backdrop" data-lightbox-close></div><div class="lightbox-dialog" role="dialog" aria-modal="true" aria-label="Xem ảnh căn hộ"><button type="button" class="lightbox-close" id="lightboxClose" aria-label="Đóng">×</button><button type="button" class="lightbox-nav lightbox-prev" id="lightboxPrev" aria-label="Ảnh trước">‹</button><img id="lightboxImage" src="" alt="Ảnh căn hộ phóng to"><button type="button" class="lightbox-nav lightbox-next" id="lightboxNext" aria-label="Ảnh tiếp theo">›</button><div class="lightbox-counter" id="lightboxCounter">1 / 1</div></div>';
    document.body.appendChild(lightbox);
    const image = lightbox.querySelector('#lightboxImage');
    const counter = lightbox.querySelector('#lightboxCounter');

    let current = 0;
    const show = (index) => {
        current = (index + buttons.length) % buttons.length;
        image.src = buttons[current].dataset.full || buttons[current].src;
        image.alt = buttons[current].dataset.alt || buttons[current].alt || 'Ảnh căn hộ phóng to';
        counter.textContent = `${current + 1} / ${buttons.length}`;
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lightbox-open');
    };
    const close = () => {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lightbox-open');
        image.removeAttribute('src');
    };

    buttons.forEach((button, index) => button.addEventListener('click', () => show(index)));
    document.getElementById('lightboxClose')?.addEventListener('click', close);
    document.getElementById('lightboxPrev')?.addEventListener('click', () => show(current - 1));
    document.getElementById('lightboxNext')?.addEventListener('click', () => show(current + 1));
    lightbox.querySelector('[data-lightbox-close]')?.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
        if (!lightbox.classList.contains('is-open')) return;
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowLeft') show(current - 1);
        if (event.key === 'ArrowRight') show(current + 1);
    });
})();