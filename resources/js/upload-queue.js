const UPLOAD_CONFIG = {
    maxConcurrent: 3,
    maxRetries: 3,
    retryDelays: [1000, 3000, 5000],
};

class UploadQueue {
    constructor(config = {}) {
        this.config = { ...UPLOAD_CONFIG, ...config };
        this.files = new Map();
        this.activeUploads = 0;
        this.queue = [];
        this.onFileStatusChange = null;
        this.onProgressUpdate = null;
    }

    addFile(file, mediaId, uploadUri, strategy) {
        const fileEntry = {
            file,
            mediaId,
            uploadUri,
            strategy,
            status: 'pending',
            progress: 0,
            uploadedBytes: 0,
            error: null,
            retries: 0,
        };
        this.files.set(mediaId, fileEntry);
        this.queue.push(mediaId);
        this.processQueue();
        return fileEntry;
    }

    removeFile(mediaId) {
        const entry = this.files.get(mediaId);
        if (entry && entry.status === 'uploading') {
            return false;
        }
        this.files.delete(mediaId);
        this.queue = this.queue.filter(id => id !== mediaId);
        this.notifyChange(mediaId);
        return true;
    }

    retryFile(mediaId) {
        const entry = this.files.get(mediaId);
        if (!entry || entry.status !== 'failed') return;

        entry.status = 'pending';
        entry.progress = 0;
        entry.uploadedBytes = 0;
        entry.error = null;
        entry.retries = 0;
        this.queue.push(mediaId);
        this.notifyChange(mediaId);
        this.processQueue();
    }

    hasActiveUploads() {
        for (const entry of this.files.values()) {
            if (entry.status === 'uploading' || entry.status === 'pending') {
                return true;
            }
        }
        return false;
    }

    hasUploadedFiles() {
        for (const entry of this.files.values()) {
            if (entry.status === 'done') return true;
        }
        return false;
    }

    hasFailedFiles() {
        for (const entry of this.files.values()) {
            if (entry.status === 'failed') return true;
        }
        return false;
    }

    async processQueue() {
        while (this.activeUploads < this.config.maxConcurrent && this.queue.length > 0) {
            const mediaId = this.queue.shift();
            const entry = this.files.get(mediaId);
            if (!entry || entry.status !== 'pending') continue;

            this.activeUploads++;
            entry.status = 'uploading';
            this.notifyChange(mediaId);

            if (entry.strategy === 'plan_a') {
                await this.uploadPlanA(entry);
            } else {
                await this.uploadPlanB(entry);
            }

            this.activeUploads--;
            this.processQueue();
        }
    }

    async uploadPlanA(entry) {
        try {
            const response = await fetch(entry.uploadUri, {
                method: 'PUT',
                body: entry.file,
                headers: {
                    'Content-Type': entry.file.type,
                },
            });

            if (!response.ok) {
                throw new Error(`Upload failed with status ${response.status}`);
            }

            const completeResponse = await fetch('/api/upload/complete', {
                method: 'POST',
                headers: this.getApiHeaders(),
                body: JSON.stringify({ media_id: entry.mediaId }),
            });

            if (!completeResponse.ok) {
                const data = await completeResponse.json().catch(() => ({}));
                throw new Error(data.message || 'Completion verification failed');
            }

            entry.status = 'done';
            entry.progress = 100;
            this.notifyChange(entry.mediaId);
        } catch (error) {
            await this.handleUploadError(entry, error);
        }
    }

    async uploadPlanB(entry) {
        const chunkSize = this.config.chunkSize || 8 * 1024 * 1024;
        const file = entry.file;
        const totalSize = file.size;
        let offset = entry.uploadedBytes || 0;

        try {
            while (offset < totalSize) {
                const chunk = file.slice(offset, offset + chunkSize);
                const chunkData = await chunk.arrayBuffer();

                const url = `/api/upload/chunk?media_id=${entry.mediaId}&offset=${offset}&total_size=${totalSize}`;

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Upload-Nonce': window.uploadNonce || '',
                        'X-Upload-Token': window.uploadToken || '',
                        'Content-Type': 'application/octet-stream',
                    },
                    body: chunkData,
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    throw new Error(data.message || `Chunk upload failed at offset ${offset}`);
                }

                const result = await response.json();

                if (result.completed) {
                    entry.status = 'done';
                    entry.progress = 100;
                    entry.uploadedBytes = totalSize;
                    this.notifyChange(entry.mediaId);
                    return;
                }

                offset = result.uploaded_bytes || (offset + chunkData.byteLength);
                entry.uploadedBytes = offset;
                entry.progress = Math.round((offset / totalSize) * 100);
                this.notifyChange(entry.mediaId);
            }
        } catch (error) {
            await this.handleUploadError(entry, error);
        }
    }

    async handleUploadError(entry, error) {
        entry.retries++;

        if (entry.retries <= this.config.maxRetries) {
            entry.status = 'pending';
            const delay = this.config.retryDelays[entry.retries - 1] || 5000;
            this.notifyChange(entry.mediaId);

            setTimeout(() => {
                this.queue.push(entry.mediaId);
                this.processQueue();
            }, delay);
        } else {
            entry.status = 'failed';
            entry.error = error.message;
            this.notifyChange(entry.mediaId);
        }
    }

    getApiHeaders() {
        return {
            'Content-Type': 'application/json',
            'X-Upload-Nonce': window.uploadNonce || '',
            'X-Upload-Token': window.uploadToken || '',
        };
    }

    notifyChange(mediaId) {
        if (this.onFileStatusChange) {
            this.onFileStatusChange(mediaId, this.files.get(mediaId));
        }
    }
}

window.UploadQueue = UploadQueue;
window.UPLOAD_CONFIG = UPLOAD_CONFIG;
export default UploadQueue;
