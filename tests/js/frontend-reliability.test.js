import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.window = {
    uploadNonce: 'nonce',
    uploadToken: 'token',
};

const { default: UploadQueue } = await import('../../resources/js/upload-queue.js');
const { default: VoiceRecorder } = await import('../../resources/js/voice-recorder.js');

class FakeMediaRecorder {
    static instances = [];

    static isTypeSupported() {
        return true;
    }

    constructor(stream, options = {}) {
        this.stream = stream;
        this.mimeType = options.mimeType || '';
        this.state = 'inactive';
        FakeMediaRecorder.instances.push(this);
    }

    start() {
        this.state = 'recording';
    }

    stop() {
        this.state = 'inactive';
    }

    emitData(value) {
        this.ondataavailable?.({ data: new Blob([value], { type: this.mimeType }) });
    }

    emitStop() {
        this.onstop?.();
    }
}

function createStream() {
    const track = {
        stopped: false,
        stop() {
            this.stopped = true;
        },
    };

    return {
        track,
        getTracks: () => [track],
    };
}

function setNavigatorStreams(streams) {
    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        value: {
            mediaDevices: {
                getUserMedia: async () => streams.shift(),
            },
        },
    });
}

async function waitFor(predicate, timeoutMs = 500) {
    const deadline = Date.now() + timeoutMs;
    while (!predicate()) {
        if (Date.now() >= deadline) throw new Error('Condition was not reached before timeout');
        await new Promise(resolve => setTimeout(resolve, 5));
    }
}

test('stale recorder callbacks cannot complete or clean up a newer session', async () => {
    FakeMediaRecorder.instances = [];
    globalThis.MediaRecorder = FakeMediaRecorder;
    const firstStream = createStream();
    const secondStream = createStream();
    setNavigatorStreams([firstStream, secondStream]);
    const completions = [];
    const recorder = new VoiceRecorder({
        onComplete: blob => completions.push(blob),
    });

    assert.equal(await recorder.start(), true);
    const firstRecorder = FakeMediaRecorder.instances[0];
    recorder.stop();
    assert.equal(await recorder.start(), true);
    const secondRecorder = FakeMediaRecorder.instances[1];

    firstRecorder.emitData('old session');
    firstRecorder.emitStop();
    assert.equal(completions.length, 0);
    assert.equal(secondStream.track.stopped, false);
    assert.equal(recorder.currentSession?.recorder, secondRecorder);

    secondRecorder.emitData('current session');
    recorder.stop();
    secondRecorder.emitStop();
    assert.equal(completions.length, 1);
    assert.equal(secondStream.track.stopped, true);
});

test('cancelled recordings clean up without completing', async () => {
    FakeMediaRecorder.instances = [];
    globalThis.MediaRecorder = FakeMediaRecorder;
    const stream = createStream();
    setNavigatorStreams([stream]);
    let completions = 0;
    const recorder = new VoiceRecorder({
        onComplete: () => completions++,
    });

    assert.equal(await recorder.start(), true);
    const mediaRecorder = FakeMediaRecorder.instances[0];
    mediaRecorder.emitData('cancelled session');
    recorder.cancel();
    mediaRecorder.emitStop();

    assert.equal(completions, 0);
    assert.equal(stream.track.stopped, true);
    assert.equal(recorder.currentSession, null);
});

test('recorder setup failures release an acquired microphone stream', async () => {
    const stream = createStream();
    setNavigatorStreams([stream]);
    globalThis.MediaRecorder = class {
        static isTypeSupported() {
            return true;
        }

        constructor() {
            throw new Error('recorder setup failed');
        }
    };
    let errors = 0;
    const recorder = new VoiceRecorder({ onError: () => errors++ });

    assert.equal(await recorder.start(), false);
    assert.equal(errors, 1);
    assert.equal(stream.track.stopped, true);
    assert.equal(recorder.currentSession, null);
});

for (const strategy of ['plan_a', 'plan_b']) {
    test(`${strategy} request timeouts use retries and release the active slot`, async () => {
        let fetchCalls = 0;
        globalThis.fetch = (_url, options) => {
            fetchCalls++;
            return new Promise((_resolve, reject) => {
                options.signal.addEventListener('abort', () => {
                    const error = new Error('Upload request timed out');
                    error.name = 'AbortError';
                    reject(error);
                }, { once: true });
            });
        };

        const queue = new UploadQueue({
            maxConcurrent: 1,
            maxRetries: 1,
            retryDelays: [1],
            requestTimeout: 5,
            chunkSize: 4,
        });
        const file = {
            size: 4,
            type: 'image/jpeg',
            slice: () => new Blob(['data']),
        };

        queue.addFile(file, strategy, 'https://upload.example.test/session', strategy);
        await waitFor(() => queue.files.get(strategy)?.status === 'failed');

        assert.equal(fetchCalls, 2);
        assert.equal(queue.activeUploads, 0);
        assert.equal(queue.files.get(strategy).error, 'Upload request timed out');
    });
}
