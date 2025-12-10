import { toast } from "./toast.js";

export default function showErrors(e) {
    try {
        if (e.response) {
            if (e.response.status === 422) {
                Object.keys(e.response.data.errors).forEach(key => {
                    toast(e.response.status, e.response.data.errors[key][0])
                });
            } else {
                toast(e.response.status, e.response.data.message)
            }
        }
    } catch (err) {
        console.log(err);
    }
}
