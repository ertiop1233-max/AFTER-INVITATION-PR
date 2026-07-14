class VoiceRecorder {
    constructor(options = {}) {
        this.maxDuration = options.maxDuration || 600;
        this.mediaRecorder = null;
        this.chunks = [];
        this.stream = null;
        this.startTime = 0;
        this.timerInterval = null;
        this.isRecording = false;
        this.currentSession = null;
        this.onStateChange = options.onStateChange || (() => {});
        this.onComplete = options.onComplete || (() => {});
        this.onError = options.onError || (() => {});
    }

    async start() {
        this.retireCurrentSession();

        const session = {
            recorder: null,
            chunks: [],
            stream: null,
            startTime: 0,
            timerInterval: null,
            cancelled: false,
        };
        this.currentSession = session;

        try {
            session.stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            if (this.currentSession !== session) {
                this.cleanup(session);
                return false;
            }

            const mimeType = this.selectMimeType();
            session.recorder = new MediaRecorder(session.stream, mimeType ? { mimeType } : {});
            this.syncPublicState(session);

            session.recorder.ondataavailable = (e) => {
                if (e.data.size > 0) session.chunks.push(e.data);
            };

            session.recorder.onstop = () => {
                const recordedMimeType = session.recorder?.mimeType || '';
                const blob = new Blob(session.chunks, { type: recordedMimeType });
                const duration = Math.round((Date.now() - session.startTime) / 1000);

                try {
                    if (this.currentSession === session && !session.cancelled && blob.size > 0) {
                        this.onComplete(blob, recordedMimeType, duration);
                    }
                } finally {
                    this.cleanup(session);
                }
            };

            session.recorder.onerror = (e) => {
                session.cancelled = true;
                try {
                    if (this.currentSession === session) {
                        this.onError(e.error);
                    }
                } finally {
                    this.cleanup(session);
                }
            };

            session.recorder.start();
            session.startTime = Date.now();
            this.syncPublicState(session);
            this.isRecording = true;
            this.startTimer(session);
            this.onStateChange('recording');
            return true;
        } catch (error) {
            const isCurrentSession = this.currentSession === session;
            this.cleanup(session);
            if (isCurrentSession) this.onError(error);
            return false;
        }
    }

    stop() {
        const session = this.currentSession;
        if (session?.recorder?.state === 'recording') {
            session.cancelled = false;
            session.recorder.stop();
            this.isRecording = false;
            this.stopTimer(session);
            this.onStateChange('stopped');
        }
    }

    cancel() {
        const session = this.currentSession;
        if (session?.recorder?.state === 'recording') {
            session.cancelled = true;
            session.recorder.stop();
            this.isRecording = false;
            this.stopTimer(session);
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

    startTimer(session) {
        session.timerInterval = setInterval(() => {
            if (this.currentSession !== session) {
                this.stopTimer(session);
                return;
            }

            const elapsed = Math.round((Date.now() - session.startTime) / 1000);
            this.onStateChange('recording', elapsed);

            if (elapsed >= this.maxDuration) {
                this.stop();
            }
        }, 1000);
        this.timerInterval = session.timerInterval;
    }

    stopTimer(session = this.currentSession) {
        if (session?.timerInterval) {
            clearInterval(session.timerInterval);
            session.timerInterval = null;
        }
        if (this.currentSession === session) {
            this.timerInterval = null;
        }
    }

    retireCurrentSession() {
        const session = this.currentSession;
        if (!session) return;

        session.cancelled = true;
        this.stopTimer(session);
        if (session.recorder?.state === 'recording') {
            session.recorder.stop();
        } else {
            this.cleanup(session);
        }
    }

    cleanup(session = this.currentSession) {
        if (!session) return;

        this.stopTimer(session);
        session.stream?.getTracks().forEach(track => track.stop());
        session.stream = null;
        session.chunks = [];

        if (this.currentSession === session) {
            this.currentSession = null;
            this.isRecording = false;
            this.mediaRecorder = null;
            this.stream = null;
            this.chunks = [];
            this.startTime = 0;
        }
    }

    syncPublicState(session) {
        if (this.currentSession !== session) return;

        this.mediaRecorder = session.recorder;
        this.stream = session.stream;
        this.chunks = session.chunks;
        this.startTime = session.startTime;
    }
}

window.VoiceRecorder = VoiceRecorder;
export default VoiceRecorder;
