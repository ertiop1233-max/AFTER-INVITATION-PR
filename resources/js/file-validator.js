const SUPPORTED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
const SUPPORTED_VIDEO_MIMES = ['video/mp4', 'video/quicktime', 'video/webm'];

const fileValidator = {
    isSupported(file) {
        return SUPPORTED_IMAGE_MIMES.includes(file.type) || SUPPORTED_VIDEO_MIMES.includes(file.type);
    },

    isImage(file) {
        return SUPPORTED_IMAGE_MIMES.includes(file.type);
    },

    isVideo(file) {
        return SUPPORTED_VIDEO_MIMES.includes(file.type);
    },

    isHeic(file) {
        return file.type === 'image/heic' || file.type === 'image/heif';
    },

    getMediaType(file) {
        if (this.isImage(file)) return 'photo';
        if (this.isVideo(file)) return 'video';
        return null;
    },

    getExtension(filename) {
        const parts = filename.split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    },

    validateFile(file, maxSize, allowedTypes) {
        const errors = [];

        if (!this.isSupported(file)) {
            errors.push('Unsupported file type.');
        }

        if (file.size > maxSize) {
            errors.push(`File exceeds maximum size of ${utils.formatBytes(maxSize)}.`);
        }

        if (allowedTypes && !allowedTypes.includes(this.getMediaType(file))) {
            errors.push('This file type is not allowed for this event.');
        }

        return errors;
    },

    async generateThumbnail(file) {
        if (this.isHeic(file)) {
            try {
                const heic2any = (await import('heic2any')).default;
                const blob = await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.5 });
                return await this.blobToDataURL(blob);
            } catch (e) {
                console.warn('HEIC thumbnail generation failed:', e);
                return null;
            }
        }

        if (this.isImage(file)) {
            return await this.generateImageThumbnail(file);
        }

        if (this.isVideo(file)) {
            return await this.generateVideoThumbnail(file);
        }

        return null;
    },

    async generateImageThumbnail(file) {
        return new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const maxDim = 300;
                let { width, height } = img;

                if (width > height) {
                    if (width > maxDim) {
                        height = (height * maxDim) / width;
                        width = maxDim;
                    }
                } else {
                    if (height > maxDim) {
                        width = (width * maxDim) / height;
                        height = maxDim;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                resolve(canvas.toDataURL('image/jpeg', 0.7));
            };
            img.onerror = () => resolve(null);
            img.src = URL.createObjectURL(file);
        });
    },

    async generateVideoThumbnail(file) {
        return new Promise((resolve) => {
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.muted = true;
            video.playsInline = true;

            video.onloadeddata = () => {
                video.currentTime = Math.min(1, video.duration / 2);
            };

            video.onseeked = () => {
                const canvas = document.createElement('canvas');
                const maxDim = 300;
                let { videoWidth: width, videoHeight: height } = video;

                if (width > height) {
                    if (width > maxDim) {
                        height = (height * maxDim) / width;
                        width = maxDim;
                    }
                } else {
                    if (height > maxDim) {
                        width = (width * maxDim) / height;
                        height = maxDim;
                    }
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, width, height);
                resolve(canvas.toDataURL('image/jpeg', 0.7));
            };

            video.onerror = () => resolve(null);
            video.src = URL.createObjectURL(file);
        });
    },

    async blobToDataURL(blob) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => resolve(null);
            reader.readAsDataURL(blob);
        });
    },
};

window.fileValidator = fileValidator;
export default fileValidator;
