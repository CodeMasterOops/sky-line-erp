import {useDateHelper} from "@/composables/dateHelper.js";
import {useDatePreferenceStore} from "@/stores/admin/datePreference.js";
import {displayDate, displayDateTime} from "@/composables/useDisplayDate.js";

/**
 * Format a date for display, honoring the user's AD/BS calendar preference.
 * In BS mode the `format` argument is ignored and a Bikram Sambat date is shown.
 * @param {string|null} value  - ISO date or datetime string (e.g. "2026-04-25T00:00:00.000000Z" or "2026-04-25")
 * @param {string} format      - dayjs format string used in AD mode, default "DD MMM YYYY"
 * @returns {string}
 */
export const formatDate = (value, format = 'DD MMM YYYY') => {
    if (!value) return '–';
    return displayDate(value, useDatePreferenceStore().mode, {adFormat: format});
};

/**
 * Format a datetime (date + time) for display, honoring the AD/BS preference.
 */
export const formatDateTime = (value, format = 'DD MMM YYYY, h:mm A') => {
    if (!value) return '–';
    return displayDateTime(value, useDatePreferenceStore().mode, {adFormat: format});
};

export const containsHtmlTag = (str) => {
    const div = document.createElement('div');
    div.innerHTML = str;
    return div.children.length > 0;
}

export const extractBaseUrl = (fullUrl) => {
    const urlObj = new URL(fullUrl);
    return `${urlObj.protocol}//${urlObj.host}`;
}

export const convertToNepali = (number = '') => {
    const devanagariDigits = {
        '0': '०', '1': '१', '2': '२', '3': '३',
        '4': '४', '5': '५', '6': '६', '7': '७',
        '8': '८', '9': '९'
    };

    return number.toString().replace(/[0-9]/g, (digit) => devanagariDigits[digit]);
}

export const fileExtension = (file) => {
    return file.split('.').pop().split('?')[0];
}

export const isImage = (file) => {
    return ['jpg', 'jpeg', 'png', 'webp', 'svg'].includes(fileExtension(file));
}

export const isPdf = (file) => {
    return ['pdf'].includes(fileExtension(file));
}

export const adToBsDate = (adDate, format = 'en', separator = '-') => {
    if (!adDate) {
        return '';
    }
    const {adToBs} = useDateHelper();

    const convertedDate = adToBs(adDate);

    const bsDate = convertedDate.replace(/-/g, separator);

    if (format === 'np') {
        return convertToNepali(bsDate);
    }

    return bsDate;
};

export { formatMoney as formatAmount, formatSignedAmount } from '@/helpers/formatMoney.js';

export const sanitizeDownloadFilename = (filename) => String(filename ?? '').replace(/[\\/]/g, '-');
