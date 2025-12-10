import {ref} from "vue";

export const useYup = (form, validations) => {

    const errors = ref({})

    const catchYupErrors = (err) => {
        errors.value = {}
        err.inner.forEach((error) => {
            Object.assign(errors.value, {
                [error.path]: error.message
            })
        });
    }

    const validateField = (field) => {
        validations
            .validateAt(field, form)
            .then(() => {
                delete errors.value[field];
            })
            .catch(err => {
                errors.value[field] = err.message;
            });
    }

    const validateForm = () => {
        return new Promise((resolve) => {
            validations
                .validate(form, {abortEarly: false})
                .then(() => {
                    resolve(true);
                })
                .catch(err => {
                    catchYupErrors(err);
                    resolve(false);
                });
        });
    };

    return {
        validateForm,
        validateField,
        errors
    }
}
