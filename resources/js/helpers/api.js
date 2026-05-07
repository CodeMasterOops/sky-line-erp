import axios from "axios";
import {useAdminAuthStore} from "@/stores/admin/auth";
import {useRouter} from "vue-router";
import {useSuperAdminAuthStore} from "@/stores/super-admin/auth.js";
import {storeToRefs} from "pinia";
import {useBranchStore} from "@/stores/admin/settings/branch.js";

const router = useRouter();

const baseUrl = `${window.location.origin}/api`;

const apiFront = (url, method = 'get', body = {}, options = {}) => {
    const axiosBase = axios.create({
        baseURL: baseUrl,
        ...options
    });

    return formattedRequest(axiosBase, method, url, body)
}

const apiAdmin = (url, method = 'get', body = {}, options = {}) => {
    const axiosBase = axios.create({
        baseURL: `${baseUrl}/admin/`,
        ...options
    });

    const branchId = useBranchStore().selectedBranchId;

    axiosBase.interceptors.request.use(config => {
        config.headers.Authorization = `Bearer ${useAdminAuthStore().authUser.access_token}`;
        if (branchId) {
            config.headers['X-Branch-Id'] = branchId;
        }
        return config;
    })

    axiosBase.interceptors.response.use(response => {
        return response;
    }, async error => {
        if (error.response.status === 401) {
            useAdminAuthStore().removeAuthToken();
            await router.push({name: 'admin.login'});
        }
        throw error;
    })

    return formattedRequest(axiosBase, method, url, body)
}

const apiSuperAdmin = (url, method = 'get', body = {}, options = {}) => {
    const axiosBase = axios.create({
        baseURL: `${baseUrl}/super-admin/`,
        ...options
    });

    axiosBase.interceptors.request.use(config => {
        config.headers.Authorization = `Bearer ${useSuperAdminAuthStore().authUser.access_token}`
        return config;
    })

    axiosBase.interceptors.response.use(response => {
        return response;
    }, async error => {
        if (error.response.status === 401) {
            useSuperAdminAuthStore().removeAuthToken();
            await router.push({name: 'super-admin.login'});
        }
        throw error;
    })

    return formattedRequest(axiosBase, method, url, body)
}

const formattedRequest = (base, method, url, body = null) => {
    switch (method) {
        case 'post': {
            return body ? base.post(url, body) : base.post(url);
        }
        case 'put': {
            return body ? base.put(url, body) : base.put(url);
        }
        case 'delete': {
            return base.delete(url);
        }
        default:
            return body ? base.get(url, {params: body}) : base.get(url);
    }
}

/**
 * Download a file (PDF, CSV, etc.) from an authenticated admin endpoint.
 * Handles the blob response and triggers a browser download.
 *
 * @param {string} url  - relative URL under /api/admin/
 * @param {string} filename - suggested download filename
 * @param {object} params   - query params (GET request)
 */
const downloadAdminFile = async (url, filename, params = {}) => {
    const token = useAdminAuthStore().authUser.access_token;
    const response = await axios.get(`${baseUrl}/admin/${url}`, {
        headers: {Authorization: `Bearer ${token}`},
        params,
        responseType: 'blob',
    });
    const blob = new Blob([response.data], {type: response.headers['content-type']});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
};

export {apiFront, apiAdmin, apiSuperAdmin, downloadAdminFile}
