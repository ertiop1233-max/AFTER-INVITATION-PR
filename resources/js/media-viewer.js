class MediaViewer {
    constructor() {
        this.items = [];
        this.currentIndex = 0;
        this.container = null;
        this.focusedBeforeOpen = null;
    }

    open(items, startIndex = 0) {
        this.focusedBeforeOpen = document.activeElement;
        this.items = items;
        this.currentIndex = startIndex;
        this.render();
    }

    close() {
        if (this.container) {
            this.container.remove();
            this.container = null;
        }
        document.removeEventListener('keydown', this.handleKeyDown);
        document.body.style.overflow = '';
        if (this.focusedBeforeOpen) {
            this.focusedBeforeOpen.focus();
        }
    }

    next() {
        this.currentIndex = (this.currentIndex + 1) % this.items.length;
        this.updateContent();
    }

    prev() {
        this.currentIndex = (this.currentIndex - 1 + this.items.length) % this.items.length;
        this.updateContent();
    }

    render() {
        this.close();

        const overlay = document.createElement('div');
        overlay.className = 'lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Media viewer');

        const hasMultiple = this.items.length > 1;

        overlay.innerHTML = `
            <button class="lightbox-close" aria-label="Close media viewer" type="button">&times;</button>
            ${hasMultiple ? `
                <button class="lightbox-nav lightbox-prev" aria-label="Previous media" type="button">&#8249;</button>
                <button class="lightbox-nav lightbox-next" aria-label="Next media" type="button">&#8250;</button>
                <div style="position:absolute;top:var(--space-4);left:50%;transform:translateX(-50%);background:var(--color-bg-elevated);padding:var(--space-1) var(--space-3);border-radius:var(--radius-full);font-size:var(--font-size-xs);color:var(--color-text-secondary)" id="lightboxPosition">1 / ${this.items.length}</div>
            ` : ''}
            <div class="lightbox-content"></div>
        `;

        const closeBtn = overlay.querySelector('.lightbox-close');
        closeBtn.addEventListener('click', () => this.close());
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this.close();
        });

        if (hasMultiple) {
            overlay.querySelector('.lightbox-prev').addEventListener('click', (e) => { e.stopPropagation(); this.prev(); });
            overlay.querySelector('.lightbox-next').addEventListener('click', (e) => { e.stopPropagation(); this.next(); });
        }

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        this.container = overlay;
        this.updateContent();
        closeBtn.focus();

        this.handleKeyDown = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); this.close(); }
            if (e.key === 'ArrowLeft' && hasMultiple) { e.preventDefault(); this.prev(); }
            if (e.key === 'ArrowRight' && hasMultiple) { e.preventDefault(); this.next(); }
            if (e.key === 'Tab') {
                const focusable = this.getFocusableElements();
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };
        document.addEventListener('keydown', this.handleKeyDown);
    }

    getFocusableElements() {
        if (!this.container) return [];
        return Array.from(this.container.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])')).filter(el => el.offsetParent !== null);
    }

    updateContent() {
        if (!this.container) return;
        const item = this.items[this.currentIndex];
        const contentEl = this.container.querySelector('.lightbox-content');
        const posEl = this.container.querySelector('#lightboxPosition');
        if (posEl) posEl.textContent = `${this.currentIndex + 1} / ${this.items.length}`;

        const downloadLink = item.download_url
            ? `<a href="${item.download_url}" class="btn btn-secondary" style="position:absolute;bottom:var(--space-4);left:50%;transform:translateX(-50%)" download>Download Original</a>`
            : '';

        const isHeic = item.mime_type && (item.mime_type === 'image/heic' || item.mime_type === 'image/heif');

        if (item.type === 'video') {
            contentEl.innerHTML = `
                <video src="${item.url}" controls autoplay playsinline></video>
                <div class="media-fallback" style="display:none">
                    <img src="${item.thumbnail_url || ''}" alt="${item.name || ''}" style="max-width:90vw;max-height:70vh;border-radius:var(--radius-md)">
                    <p style="color:var(--color-text-secondary);margin:var(--space-3) 0">This video format may not be supported by your browser.</p>
                    ${downloadLink}
                </div>
            `;
            const video = contentEl.querySelector('video');
            const fallback = contentEl.querySelector('.media-fallback');
            video.addEventListener('error', () => {
                video.style.display = 'none';
                fallback.style.display = 'flex';
                fallback.style.flexDirection = 'column';
                fallback.style.alignItems = 'center';
            });
        } else if (isHeic && item.thumbnail_url) {
            contentEl.innerHTML = `
                <img src="${item.thumbnail_url}" alt="${item.name || ''}">
                ${downloadLink}
            `;
        } else if (isHeic) {
            contentEl.innerHTML = `
                <div style="text-align:center">
                    <p style="color:var(--color-text-secondary);margin-bottom:var(--space-3)">
                        HEIC images may not display in all browsers.
                    </p>
                    ${downloadLink}
                </div>
            `;
        } else {
            contentEl.innerHTML = `<img src="${item.url}" alt="${item.name || ''}">`;
        }
    }
}

window.mediaViewer = new MediaViewer();
export default MediaViewer;