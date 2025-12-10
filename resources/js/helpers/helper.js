import {useRoute} from "vue-router";

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

export const stringHeadline = (string) => {
    return string
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

export const generateSlug = (text, separator = '-') => {
    return text
        .toLowerCase()
        .trim()
        .replace(/[\s\W-]+/g, separator);
};

export const statusColor = (status) => {
    let color = '';

    switch (status) {
        case 'pending':
            color = 'warning';
            break;
        case 'waiting-list':
            color = 'secondary';
            break;
        case 'cancelled':
            color = 'danger';
            break;
        case 'confirmed':
            color = 'success';
            break;
        default:
            color = 'primary'
    }

    return color;
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

export const siteUrl = import.meta.env.VITE_SITE_URL;
export const editorFileSelectPath = '/admin/editor-file-select';
export const vendorEditorFileSelectPath = '/vendor/editor-file-select';
export const isVendorRoute =()=>{
    const route=useRoute();
    return route.path.startsWith('/vendor/');
}
