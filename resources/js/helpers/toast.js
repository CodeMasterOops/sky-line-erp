import {useToast} from 'vue-toastification'
import "vue-toastification/dist/index.css";
import Swal from "sweetalert2";

export const toast = (status, title) => {
    const errors = [400, 401, 403, 404, 405, 408, 414, 415, 422, 429, 500]
    const successStatuses = [200, 201, 202, 204]
    const message =
        title !== undefined && title !== null && title !== ''
            ? title
            : successStatuses.includes(status)
                ? 'Success'
                : 'Something went wrong'

    if (successStatuses.includes(status)) {
        useToast().success(message)
    } else if (errors.includes(status)) {
        useToast().error(message)
    }
}

export const sweetToast = (status, title, description) => {
    const errors = [400, 401, 403, 404, 405, 408, 414, 415, 422, 429, 500]
    if (status === 200 || status === 201) {
        Swal.fire({
            title: title,
            text: description,
            icon: 'success',
            timer: 5000,
            timerProgressBar: true
        })
    } else if (errors.includes(status)) {
        Swal.fire({
            title: title,
            text: description,
            icon: 'error',
            timer: 5000,
            timerProgressBar: true
        })
    }
}
