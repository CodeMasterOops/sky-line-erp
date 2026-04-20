import axios from "axios";
import {useAdminAuthStore} from "@/stores/admin/auth";
import {useRouter} from "vue-router";
import {useSuperAdminAuthStore} from "@/stores/super-admin/auth.js";

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

    axiosBase.interceptors.request.use(config => {
        config.headers.Authorization = `Bearer ${useAdminAuthStore().authUser.access_token}`
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

export {apiFront, apiAdmin, apiSuperAdmin}
