import moment from 'moment';

/**
 * Format a date or datetime string for display.
 * @param {string|null} value  - ISO date or datetime string (e.g. "2026-04-25T00:00:00.000000Z" or "2026-04-25")
 * @param {string} format      - moment.js format string, default "DD MMM YYYY"
 * @returns {string}
 */
export const formatDate = (value, format = 'DD MMM YYYY') => {
    if (!value) return '–';
    return moment(value).format(format);
};

/**
 * Format a datetime string showing date + time.
 */
export const formatDateTime = (value, format = 'DD MMM YYYY, h:mm A') => {
    if (!value) return '–';
    return moment(value).format(format);
};

export const storedPermissions = () => {
    let permissions = [];
    if (localStorage.getItem('permissions')) {
        permissions = JSON.parse(atob(localStorage.getItem('permissions')));
    }
    return permissions;
}

export const containsHtmlTag=(str)=>{
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
