import { toast } from "./toast.js";

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
            } else if (data.message) {
                toast(status, data.message);
            } else {
                toast(status, 'Request failed.');
            }
        }
    } catch (err) {
        console.log(err);
    }
}
