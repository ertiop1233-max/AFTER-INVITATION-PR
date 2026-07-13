@extends('layouts.upload')

@section('upload-content')
<div x-data="uploadApp()" x-init="init()">
    <div class="upload-step">
        <label class="upload-step-label" for="contributorName">Your Name</label>
        <input
            class="name-input"
            type="text"
            id="contributorName"
            x-model="contributorName"
            @input="validateName()"
            :disabled="submitting"
            placeholder="Enter your name to begin"
            aria-describedby="nameHelp"
        >
        <p id="nameHelp" style="font-size:var(--font-size-xs);color:var(--color-text-muted);margin-top:var(--space-2)" x-show="nameError" x-cloak>
            <span x-text="nameError"></span>
        </p>
    </div>

    <div class="upload-step" x-show="nameValid" x-cloak>
        <label class="upload-step-label">Add Your Memories</label>
        <div
            class="dropzone"
            :class="{ 'disabled': !nameValid || preparing }"
            @click="$refs.fileInput.click()"
            @dragover.prevent="dragover = true"
            @dragleave.prevent="dragover = false"
            @drop.prevent="handleDrop($event)"
            role="button"
            tabindex="0"
            aria-label="Select files to upload"
        >
            <div class="dropzone-icon">&#128247;</div>
            <div class="dropzone-text">
                <strong>Click to select</strong> or drag files here
                <br>
                <span style="font-size:var(--font-size-xs)">Photos and videos up to {{ utils.formatBytes(maxSubmissionBytes) }}</span>
            </div>
        </div>
        <input type="file" x-ref.fileInput multiple accept="image/*,video/*" style="display:none" @change="handleFileSelect($event)">
    </div>

    <div class="file-list" x-show="files.length > 0" x-cloak role="list" aria-live="polite">
        <template x-for="file in files" :key="file.id">
            <div class="file-item" role="listitem">
                <img x-show="file.thumbnail" :src="file.thumbnail" class="file-thumbnail" alt="">
                <div class="file-info">
                    <div class="file-name" x-text="file.name"></div>
                    <div class="file-size" x-text="utils.formatBytes(file.size)"></div>
                    <div class="file-progress" x-show="file.status === 'uploading'">
                        <div class="file-progress-bar" :style="`width: ${file.progress}%`"></div>
                    </div>
                    <div x-show="file.status === 'failed'" style="color:var(--color-error);font-size:var(--font-size-xs);margin-top:var(--space-1)" x-text="file.error"></div>
                </div>
                <div class="file-status" :class="file.status" x-text="file.status === 'done' ? '&#10003;' : (file.status === 'failed' ? '!' : '')"></div>
                <div class="file-actions" x-show="file.status === 'failed'">
                    <button class="file-action-btn" @click="retryFile(file)" aria-label="Retry upload" title="Retry">&#8635;</button>
                    <button class="file-action-btn" @click="removeFile(file)" aria-label="Remove file" title="Remove">&times;</button>
                </div>
            </div>
        </template>
    </div>

    @if($event->allow_voice)
    <div class="voice-section" x-show="nameValid" x-cloak>
        <label class="upload-step-label">Record a Voice Message (optional)</label>
        <div class="voice-recorder">
            <button
                class="record-button"
                :class="{ 'recording': recording }"
                @click="toggleRecording()"
                :disabled="submitting"
                aria-label="Record voice message"
                type="button"
            >
                <span x-show="!recording">&#127908;</span>
                <span x-show="recording">&#9632;</span>
            </button>
            <div class="record-timer" x-text="formatTime(elapsed)" x-show="recording || hasVoice">0:00</div>
            <div class="record-limit" x-show="!recording && !hasVoice">Max {{ Math.floor(voiceMaxDuration / 60) }} minutes</div>
            <button x-show="hasVoice && !recording" @click="deleteVoice()" class="btn btn-ghost" style="min-height:auto" type="button">Delete Recording</button>
        </div>
    </div>
    @endif

    @if($event->allow_messages)
    <div class="message-section" x-show="nameValid" x-cloak>
        <label class="upload-step-label">Write a Message (optional)</label>
        <textarea
            class="message-textarea"
            x-model="message"
            :disabled="submitting"
            placeholder="Share a personal message, a favorite memory, or well wishes..."
            maxlength="5000"
        ></textarea>
    </div>
    @endif

    <div class="submit-section" x-show="nameValid && canSubmit" x-cloak>
        <button
            class="submit-button"
            @click="submit()"
            :disabled="submitting || !canSubmit"
            type="button"
        >
            <span x-show="!submitting">Submit Memories</span>
            <span x-show="submitting">Submitting...</span>
        </button>
    </div>
</div>

<script>
window.uploadToken = '{{ $uploadToken }}';
window.uploadNonce = '{{ $uploadNonce }}';
window.uploadStrategy = '{{ $uploadStrategy }}';
window.maxSubmissionBytes = {{ $maxSubmissionBytes }};
window.uploadConfig = {
    maxConcurrent: {{ $maxConcurrent }},
    maxRetries: {{ $maxRetries }},
    chunkSize: {{ $chunkSize }},
};
window.voiceMaxDuration = {{ $voiceMaxDuration }};
window.voiceMaxSize = {{ $voiceMaxSize }};
window.eventAllowsPhotos = {{ $event->allow_photos ? 'true' : 'false' }};
window.eventAllowsVideos = {{ $event->allow_videos ? 'true' : 'false' }};
window.eventAllowsVoice = {{ $event->allow_voice ? 'true' : 'false' }};
window.eventAllowsMessages = {{ $event->allow_messages ? 'true' : 'false' }};

function uploadApp() {
    return {
        contributorName: '',
        nameValid: false,
        nameError: '',
        preparing: false,
        submitting: false,
        files: [],
        fileQueue: null,
        submissionId: null,
        uploadSessionKey: null,
        message: '',
        recording: false,
        hasVoice: false,
        voiceBlob: null,
        voiceMime: null,
        voiceDuration: 0,
        elapsed: 0,
        recorder: null,
        dragover: false,
        utils: window.utils,
        maxSubmissionBytes: window.maxSubmissionBytes,
        voiceMaxDuration: window.voiceMaxDuration,

        init() {
            this.uploadSessionKey = utils.generateSessionKey();
            this.fileQueue = new UploadQueue({
                ...window.uploadConfig,
                chunkSize: window.uploadConfig.chunkSize,
            });
            this.fileQueue.onFileStatusChange = (mediaId, entry) => {
                const file = this.files.find(f => f.id === mediaId);
                if (file) {
                    file.status = entry.status;
                    file.progress = entry.progress;
                    file.error = entry.error;
                }
            };

            this.loadExistingSession();

            window.addEventListener('beforeunload', (e) => {
                if (this.fileQueue && this.fileQueue.hasActiveUploads()) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        loadExistingSession() {
            const existing = uploadSession.load(window.uploadToken);
            if (existing) {
                this.submissionId = existing.submission_id;
                this.uploadSessionKey = existing.upload_session_key;
            }
        },

        validateName() {
            const name = this.contributorName.trim();
            if (name.length < 4) {
                this.nameValid = false;
                this.nameError = 'Name must be at least 4 characters.';
            } else if (!/^[\pL\pN\s]+$/u.test(name)) {
                this.nameValid = false;
                this.nameError = 'Name can only contain letters, numbers, and spaces.';
            } else {
                this.nameValid = true;
                this.nameError = '';
            }
        },

        async handleFileSelect(event) {
            const selectedFiles = Array.from(event.target.files);
            await this.processFiles(selectedFiles);
            event.target.value = '';
        },

        async handleDrop(event) {
            this.dragover = false;
            const droppedFiles = Array.from(event.dataTransfer.files);
            await this.processFiles(droppedFiles);
        },

        async processFiles(selectedFiles) {
            if (!this.nameValid) return;

            if (!this.submissionId) {
                await this.ensureSubmission();
                if (!this.submissionId) return;
            }

            for (const file of selectedFiles) {
                if (!fileValidator.isSupported(file)) {
                    this.files.push({ id: Date.now() + Math.random(), name: file.name, size: file.size, status: 'failed', error: 'Unsupported file type.', progress: 0 });
                    continue;
                }

                const allowedTypes = [];
                if (window.eventAllowsPhotos) allowedTypes.push('photo');
                if (window.eventAllowsVideos) allowedTypes.push('video');
                const errors = fileValidator.validateFile(file, this.maxSubmissionBytes, allowedTypes);
                if (errors.length > 0) {
                    this.files.push({ id: Date.now() + Math.random(), name: file.name, size: file.size, status: 'failed', error: errors.join(' '), progress: 0 });
                    continue;
                }

                const thumbnail = await fileValidator.generateThumbnail(file);

                try {
                    const response = await fetch('/api/upload/init', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Upload-Nonce': window.uploadNonce,
                            'X-Upload-Token': window.uploadToken,
                        },
                        body: JSON.stringify({
                            submission_id: this.submissionId,
                            original_filename: file.name,
                            mime_type: file.type,
                            extension: fileValidator.getExtension(file.name),
                            file_size_bytes: file.size,
                        }),
                    });

                    if (!response.ok) {
                        const data = await response.json().catch(() => ({}));
                        this.files.push({ id: Date.now() + Math.random(), name: file.name, size: file.size, status: 'failed', error: data.message || 'Upload init failed.', progress: 0 });
                        continue;
                    }

                    const result = await response.json();
                    const fileId = result.media_id;

                    this.files.push({
                        id: fileId,
                        name: file.name,
                        size: file.size,
                        status: 'pending',
                        progress: 0,
                        thumbnail: thumbnail,
                    });

                    this.fileQueue.addFile(file, fileId, result.upload_uri || null, window.uploadStrategy);

                    if (thumbnail) {
                        this.uploadThumbnail(fileId, thumbnail);
                    }
                } catch (e) {
                    this.files.push({ id: Date.now() + Math.random(), name: file.name, size: file.size, status: 'failed', error: 'Network error.', progress: 0 });
                }
            }
        },

        async uploadThumbnail(mediaId, thumbnailData) {
            try {
                await fetch('/api/upload/thumbnail', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Upload-Nonce': window.uploadNonce,
                        'X-Upload-Token': window.uploadToken,
                    },
                    body: JSON.stringify({
                        media_id: mediaId,
                        thumbnail_data: thumbnailData.split(',')[1],
                    }),
                });
            } catch (e) {
                console.warn('Thumbnail upload failed:', e);
            }
        },

        async ensureSubmission() {
            this.preparing = true;
            try {
                const response = await fetch('/api/submissions/start', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Upload-Nonce': window.uploadNonce,
                        'X-Upload-Token': window.uploadToken,
                    },
                    body: JSON.stringify({
                        event_token: window.uploadToken,
                        upload_session_key: this.uploadSessionKey,
                        contributor_name: this.contributorName.trim(),
                        nonce: window.uploadNonce,
                    }),
                });

                if (response.status === 410) {
                    uploadSession.clear(window.uploadToken);
                    this.submissionId = null;
                    this.uploadSessionKey = utils.generateSessionKey();
                    this.preparing = false;
                    return;
                }

                if (!response.ok) {
                    this.preparing = false;
                    return;
                }

                const data = await response.json();

                if (data.status === 'completed') {
                    uploadSession.clear(window.uploadToken);
                    window.location.reload();
                    return;
                }

                this.submissionId = data.submission_id;
                uploadSession.save(window.uploadToken, {
                    submission_id: this.submissionId,
                    upload_session_key: this.uploadSessionKey,
                    created_at: Date.now(),
                });
            } catch (e) {
                console.error('Failed to start submission:', e);
            }
            this.preparing = false;
        },

        retryFile(file) {
            this.fileQueue.retryFile(file.id);
        },

        removeFile(file) {
            this.fileQueue.removeFile(file.id);
            this.files = this.files.filter(f => f.id !== file.id);
        },

        get canSubmit() {
            if (!this.nameValid) return false;
            if (this.preparing || this.submitting) return false;
            if (this.fileQueue && this.fileQueue.hasActiveUploads()) return false;
            const hasUploadedFiles = this.fileQueue && this.fileQueue.hasUploadedFiles();
            const hasMessage = this.message.trim().length > 0;
            const hasVoice = this.hasVoice;
            return hasUploadedFiles || hasMessage || hasVoice;
        },

        async toggleRecording() {
            if (this.recording) {
                this.recorder.stop();
            } else {
                this.recorder = new VoiceRecorder({
                    maxDuration: this.voiceMaxDuration,
                    onStateChange: (state, elapsed) => {
                        if (state === 'recording' && elapsed !== undefined) {
                            this.elapsed = elapsed;
                        }
                    },
                    onComplete: (blob, mime, duration) => {
                        if (blob.size > window.voiceMaxSize) {
                            alert('Voice recording exceeds maximum size.');
                            return;
                        }
                        this.voiceBlob = blob;
                        this.voiceMime = mime;
                        this.voiceDuration = duration;
                        this.hasVoice = true;
                    },
                    onError: (error) => {
                        console.error('Recording error:', error);
                        this.recording = false;
                    },
                });
                await this.recorder.start();
                this.recording = true;
            }
        },

        deleteVoice() {
            this.voiceBlob = null;
            this.voiceMime = null;
            this.voiceDuration = 0;
            this.hasVoice = false;
        },

        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },

        async submit() {
            this.submitting = true;

            if (!this.submissionId) {
                await this.ensureSubmission();
                if (!this.submissionId) {
                    this.submitting = false;
                    return;
                }
            }

            try {
                const body = {
                    submission_id: this.submissionId,
                    written_message: this.message || null,
                };

                if (this.hasVoice && this.voiceBlob) {
                    const base64 = await this.blobToBase64(this.voiceBlob);
                    body.voice_data = base64;
                    body.voice_mime_type = this.voiceMime;
                    body.voice_duration_seconds = this.voiceDuration;
                }

                const response = await fetch('/api/submissions/finalize', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Upload-Nonce': window.uploadNonce,
                        'X-Upload-Token': window.uploadToken,
                    },
                    body: JSON.stringify(body),
                });

                if (response.ok) {
                    uploadSession.clear(window.uploadToken);
                    this.showSuccess();
                } else {
                    const data = await response.json().catch(() => ({}));
                    alert(data.message || 'Submission failed. Please try again.');
                }
            } catch (e) {
                alert('Network error. Please check your connection and try again.');
            }
            this.submitting = false;
        },

        blobToBase64(blob) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = () => {
                    const result = reader.result.split(',')[1];
                    resolve(result);
                };
                reader.readAsDataURL(blob);
            });
        },

        showSuccess() {
            document.querySelector('.upload-page').innerHTML = `
                <div class="success-screen">
                    <div class="success-icon">&#10003;</div>
                    <h1 class="success-title">Thank You!</h1>
                    <p class="success-message">Your memories have been submitted successfully. Thank you for sharing these moments.</p>
                </div>
            `;
        },
    };
}
</script>
@endsection
