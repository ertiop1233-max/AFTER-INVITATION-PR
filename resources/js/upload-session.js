const uploadSession = {
    getKey(uploadToken) {
        return `mv_upload_session_${uploadToken}`;
    },

    save(uploadToken, data) {
        localStorage.setItem(this.getKey(uploadToken), JSON.stringify(data));
    },

    load(uploadToken) {
        const raw = localStorage.getItem(this.getKey(uploadToken));
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    },

    clear(uploadToken) {
        localStorage.removeItem(this.getKey(uploadToken));
    },

    exists(uploadToken) {
        return localStorage.getItem(this.getKey(uploadToken)) !== null;
    },
};

window.uploadSession = uploadSession;
export default uploadSession;
