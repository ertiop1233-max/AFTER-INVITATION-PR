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

        const isHeic = item.mime_type && (item.mime_type === 'image/heic' || item.mime_type === 'image/heif');
        contentEl.replaceChildren();

        if (item.type === 'video') {
            const video = document.createElement('video');
            video.src = item.url;
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;

            const fallback = document.createElement('div');
            fallback.className = 'media-fallback';
            fallback.style.display = 'none';
            fallback.appendChild(this.createImage(item.thumbnail_url || '', item.name || '', true));

            const message = document.createElement('p');
            message.style.color = 'var(--color-text-secondary)';
            message.style.margin = 'var(--space-3) 0';
            message.textContent = 'This video format may not be supported by your browser.';
            fallback.appendChild(message);

            const downloadLink = this.createDownloadLink(item.download_url);
            if (downloadLink) fallback.appendChild(downloadLink);

            contentEl.append(video, fallback);
            video.addEventListener('error', () => {
                video.style.display = 'none';
                fallback.style.display = 'flex';
                fallback.style.flexDirection = 'column';
                fallback.style.alignItems = 'center';
            });
        } else if (isHeic && item.thumbnail_url) {
            contentEl.appendChild(this.createImage(item.thumbnail_url, item.name || ''));
            const downloadLink = this.createDownloadLink(item.download_url);
            if (downloadLink) contentEl.appendChild(downloadLink);
        } else if (isHeic) {
            const fallback = document.createElement('div');
            fallback.style.textAlign = 'center';
            const message = document.createElement('p');
            message.style.color = 'var(--color-text-secondary)';
            message.style.marginBottom = 'var(--space-3)';
            message.textContent = 'HEIC images may not display in all browsers.';
            fallback.appendChild(message);
            const downloadLink = this.createDownloadLink(item.download_url);
            if (downloadLink) fallback.appendChild(downloadLink);
            contentEl.appendChild(fallback);
        } else {
            contentEl.appendChild(this.createImage(item.url, item.name || ''));
        }
    }

    createImage(src, alt, isFallback = false) {
        const image = document.createElement('img');
        image.src = src;
        image.alt = alt;
        if (isFallback) {
            image.style.maxWidth = '90vw';
            image.style.maxHeight = '70vh';
            image.style.borderRadius = 'var(--radius-md)';
        }
        return image;
    }

    createDownloadLink(url) {
        if (!url) return null;
        const link = document.createElement('a');
        link.href = url;
        link.className = 'btn btn-secondary';
        link.style.position = 'absolute';
        link.style.bottom = 'var(--space-4)';
        link.style.left = '50%';
        link.style.transform = 'translateX(-50%)';
        link.download = '';
        link.textContent = 'Download Original';
        return link;
    }
}

window.mediaViewer = new MediaViewer();
export default MediaViewer;
