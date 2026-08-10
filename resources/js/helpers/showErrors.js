import { toast } from "./toast.js";

function isSqlLikeMessage(message) {
    if (typeof message !== 'string') {
        return false;
    }

    return /SQLSTATE|SQL:|Integrity constraint/i.test(message);
}

export default function showErrors(e) {
    try {
        if (e.response) {
            const data = e.response.data || {};
            const status = e.response.status;

            if (status === 422 && data.errors && typeof data.errors === 'object') {
                Object.keys(data.errors).forEach(key => {
                    const msg = data.errors[key];
                    toast(status, Array.isArray(msg) ? msg[0] : msg);
                });
            } else if (data.message && !isSqlLikeMessage(data.message)) {
                toast(status, data.message);
            } else {
                toast(status, 'Something went wrong. Please try again.');
            }
        }
    } catch (err) {
        console.log(err);
    }
}
