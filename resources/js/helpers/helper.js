export const storedPermissions = () => {
    let permissions = [];
    if (localStorage.getItem('permissions')) {
        permissions = JSON.parse(atob(localStorage.getItem('permissions')));
    }
    return permissions;
}

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

export const formatAmount = (value) => {
    const amount = Number(value || 0);

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};
