import { reactive } from 'vue';

/**
 * Simple client-side form validation composable.
 *
 * Usage:
 *   const rules = { amount: { required: true, min: 0.01 } };
 *   const { errors, validateField, validateAll, clearErrors } = useFormValidation(form, rules);
 */
export function useFormValidation(form, rules) {
    const errors = reactive({});

    function validateField(field) {
        const rule = rules[field];
        const value = form[field];

        if (!rule) {
            delete errors[field];
            return true;
        }

        if (rule.required && (value === null || value === undefined || value === '')) {
            errors[field] = 'This field is required.';
            return false;
        }

        if (rule.min !== undefined && value !== null && value !== undefined && value !== '') {
            if (Number(value) < rule.min) {
                errors[field] = `Must be at least ${rule.min}.`;
                return false;
            }
        }

        delete errors[field];
        return true;
    }

    function validateAll() {
        let valid = true;
        for (const field of Object.keys(rules)) {
            if (!validateField(field)) {
                valid = false;
            }
        }
        return valid;
    }

    function clearErrors() {
        for (const key of Object.keys(errors)) {
            delete errors[key];
        }
    }

    return { errors, validateField, validateAll, clearErrors };
}
