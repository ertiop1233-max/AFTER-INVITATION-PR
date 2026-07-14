class VoiceRecorder {
    constructor(options = {}) {
        this.maxDuration = options.maxDuration || 600;
        this.mediaRecorder = null;
        this.chunks = [];
        this.stream = null;
        this.startTime = 0;
        this.timerInterval = null;
        this.isRecording = false;
        this.cancelled = false;
        this.onStateChange = options.onStateChange || (() => {});
        this.onComplete = options.onComplete || (() => {});
        this.onError = options.onError || (() => {});
    }

    async start() {
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            const mimeType = this.selectMimeType();
            this.mediaRecorder = new MediaRecorder(this.stream, mimeType ? { mimeType } : {});
            this.chunks = [];
            this.cancelled = false;

            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) this.chunks.push(e.data);
            };

            this.mediaRecorder.onstop = () => {
                const mimeType = this.mediaRecorder?.mimeType || '';
                const blob = new Blob(this.chunks, { type: mimeType });
                const duration = Math.round((Date.now() - this.startTime) / 1000);
                if (!this.cancelled && blob.size > 0) {
                    this.onComplete(blob, mimeType, duration);
                }
                this.cleanup();
            };

            this.mediaRecorder.onerror = (e) => {
                this.onError(e.error);
                this.cleanup();
            };

            this.mediaRecorder.start();
            this.startTime = Date.now();
            this.isRecording = true;
            this.startTimer();
            this.onStateChange('recording');
            return true;
        } catch (error) {
            this.isRecording = false;
            this.cleanup();
            this.onError(error);
            return false;
        }
    }

    stop() {
        if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
            this.cancelled = false;
            this.mediaRecorder.stop();
            this.isRecording = false;
            this.stopTimer();
            this.onStateChange('stopped');
        }
    }

    cancel() {
        if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
            this.cancelled = true;
            this.mediaRecorder.stop();
            this.isRecording = false;
            this.stopTimer();
            this.onStateChange('cancelled');
        }
    }

    selectMimeType() {
        const types = ['audio/webm', 'audio/ogg', 'audio/mp4'];
        for (const type of types) {
            if (MediaRecorder.isTypeSupported(type)) {
                return type;
            }
        }
        return null;
    }

    startTimer() {
        this.timerInterval = setInterval(() => {
            const elapsed = Math.round((Date.now() - this.startTime) / 1000);
            this.onStateChange('recording', elapsed);

            if (elapsed >= this.maxDuration) {
                this.stop();
            }
        }, 1000);
    }

    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
    }

    cleanup() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        this.stopTimer();
        this.mediaRecorder = null;
        this.chunks = [];
    }
}

window.VoiceRecorder = VoiceRecorder;
export default VoiceRecorder;
