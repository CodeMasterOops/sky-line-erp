import {storeToRefs} from "pinia";
import dayjs from "dayjs";
import {useDatePreferenceStore} from "@/stores/admin/datePreference.js";
import {useDateHelper} from "@/composables/dateHelper.js";
import {convertToNepali} from "@/helpers/helper.js";

const toNepali = (value, nepaliDigits) => (nepaliDigits ? convertToNepali(value) : value);

/**
 * Format an AD date string for display in the given calendar mode ('en' | 'ne').
 * Pure: the caller supplies the mode, so this is reusable outside components.
 */
export const displayDate = (value, mode, {adFormat = 'DD MMM YYYY', nepaliDigits = true} = {}) => {
    if (!value) {
        return '';
    }
    if (mode === 'ne') {
        const {adToBs} = useDateHelper();
        const bs = adToBs(value);
        return bs ? toNepali(bs, nepaliDigits) : '';
    }
    return dayjs(value).format(adFormat);
};

/**
 * Format an AD datetime string (date + time) for display in the given mode.
 */
export const displayDateTime = (value, mode, {adFormat = 'DD MMM YYYY, h:mm A', nepaliDigits = true} = {}) => {
    if (!value) {
        return '';
    }
    if (mode === 'ne') {
        const {adToBs} = useDateHelper();
        const bs = adToBs(value);
        if (!bs) {
            return '';
        }
        return `${toNepali(bs, nepaliDigits)}, ${dayjs(value).format('h:mm A')}`;
    }
    return dayjs(value).format(adFormat);
};

/**
 * Format an AD date as a month label ("Baisakh 2081" / "Apr 2024") in the given mode.
 */
export const displayMonth = (value, mode, {adFormat = 'MMM YYYY', nepaliDigits = true} = {}) => {
    if (!value) {
        return '';
    }
    if (mode === 'ne') {
        const {adToBs, bsMonths} = useDateHelper();
        const bs = adToBs(value);
        if (!bs) {
            return '';
        }
        const [year, month] = bs.split('-');
        const monthName = bsMonths?.[Number(month) - 1] ?? '';
        return `${monthName} ${toNepali(year, nepaliDigits)}`.trim();
    }
    return dayjs(value).format(adFormat);
};

/**
 * Preference-aware date formatter for use inside components.
 *
 * Reads the global AD/BS preference reactively and renders an AD date in the
 * chosen calendar. Pass `force: 'en' | 'ne'` to override the preference (e.g.
 * tax documents that must always be BS).
 */
export const useDisplayDate = () => {
    const store = useDatePreferenceStore();
    const {mode} = storeToRefs(store);

    const resolveMode = (force) => force ?? mode.value;

    const formatDate = (value, {force, ...opts} = {}) => displayDate(value, resolveMode(force), opts);
    const formatDateTime = (value, {force, ...opts} = {}) => displayDateTime(value, resolveMode(force), opts);
    const formatMonth = (value, {force, ...opts} = {}) => displayMonth(value, resolveMode(force), opts);

    return {mode, formatDate, formatDateTime, formatMonth};
};
