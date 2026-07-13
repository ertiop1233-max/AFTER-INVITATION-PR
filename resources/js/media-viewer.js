class MediaViewer {
    constructor() {
        this.items = [];
        this.currentIndex = 0;
        this.container = null;
    }

    open(items, startIndex = 0) {
        this.items = items;
        this.currentIndex = startIndex;
        this.render();
        this.trapFocus();
    }

    close() {
        if (this.container) {
            this.container.remove();
            this.container = null;
        }
        document.removeEventListener('keydown', this.handleKeyDown);
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

        overlay.innerHTML = `
            <button class="lightbox-close" aria-label="Close" type="button">&times;</button>
            ${this.items.length > 1 ? `
                <button class="lightbox-nav lightbox-prev" aria-label="Previous" type="button">&#8249;</button>
                <button class="lightbox-nav lightbox-next" aria-label="Next" type="button">&#8250;</button>
            ` : ''}
            <div class="lightbox-content"></div>
        `;

        overlay.querySelector('.lightbox-close').addEventListener('click', () => this.close());
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this.close();
        });

        if (this.items.length > 1) {
            overlay.querySelector('.lightbox-prev').addEventListener('click', (e) => { e.stopPropagation(); this.prev(); });
            overlay.querySelector('.lightbox-next').addEventListener('click', (e) => { e.stopPropagation(); this.next(); });
        }

        document.body.appendChild(overlay);
        this.container = overlay;
        this.updateContent();
        this.handleKeyDown = (e) => {
            if (e.key === 'Escape') this.close();
            if (e.key === 'ArrowLeft' && this.items.length > 1) this.prev();
            if (e.key === 'ArrowRight' && this.items.length > 1) this.next();
        };
        document.addEventListener('keydown', this.handleKeyDown);
    }

    updateContent() {
        if (!this.container) return;
        const item = this.items[this.currentIndex];
        const contentEl = this.container.querySelector('.lightbox-content');

        if (item.type === 'video') {
            contentEl.innerHTML = `<video src="${item.url}" controls autoplay></video>`;
        } else {
            contentEl.innerHTML = `<img src="${item.url}" alt="${item.name || ''}">`;
        }
    }

    trapFocus() {
        if (!this.container) return;
        const focusable = this.container.querySelector('.lightbox-close');
        if (focusable) focusable.focus();
    }
}

window.mediaViewer = new MediaViewer();
export default MediaViewer;
