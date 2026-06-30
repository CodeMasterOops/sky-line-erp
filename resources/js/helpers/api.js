import axios from 'axios';
import {getActivePinia} from 'pinia';
import {useAdminAuthStore} from '@/stores/admin/auth';
import {useSuperAdminAuthStore} from '@/stores/super-admin/auth.js';
import {useBranchStore} from '@/stores/admin/settings/branch.js';
import {formattedRequest} from '@/helpers/apiRequest.js';
import {sanitizeDownloadFilename} from '@/helpers/helper.js';
import {ADMIN_BRANCH_SELECT_PATH} from '@/helpers/adminPaths.js';

function piniaStore(useStore) {
    const pinia = getActivePinia();

    return pinia ? useStore(pinia) : null;
}

const baseUrl = `${window.location.origin}/api`;

const frontClient = axios.create({
    baseURL: baseUrl,
});

const adminClient = axios.create({
    baseURL: `${baseUrl}/admin/`,
});

adminClient.interceptors.request.use((config) => {
    const token = piniaStore(useAdminAuthStore)?.authUser.access_token;

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    const branchId = piniaStore(useBranchStore)?.selectedBranchId;

    if (branchId) {
        config.headers['X-Branch-Id'] = branchId;
    }

    return config;
});

adminClient.interceptors.response.use((response) => response, async (error) => {
    if (error.response?.status === 401) {
        piniaStore(useAdminAuthStore)?.removeAuthToken();
        window.location.href = '/admin/login';
    }

    // A revoked or invalid branch selection: SetTenantContext aborts 403 with
    // this exact message when the X-Branch-Id cookie points at a branch the user
    // can no longer access. Clear the stale cookie and re-prompt for an allowed
    // branch. A permission 403 carries a different message and is left untouched.
    if (
        error.response?.status === 403 &&
        error.response?.data?.message === 'You do not have access to this branch.' &&
        !window.location.pathname.startsWith(ADMIN_BRANCH_SELECT_PATH)
    ) {
        piniaStore(useBranchStore)?.clearSelectedBranch();
        window.location.href = ADMIN_BRANCH_SELECT_PATH;
    } else if (
        error.response?.status === 403 &&
        !error.config?.url?.includes('profile/permissions')
    ) {
        // A permission 403 (not the branch message above): the user's effective
        // permissions may have changed since they were last loaded — e.g. an
        // admin edited their role mid-session. Re-sync so the UI stops showing
        // controls they no longer have. Skip the permissions endpoint itself to
        // avoid a refresh loop.
        piniaStore(useAdminAuthStore)?.refreshPermissions();
    }

    throw error;
});

const superAdminClient = axios.create({
    baseURL: `${baseUrl}/super-admin/`,
});

superAdminClient.interceptors.request.use((config) => {
    const token = piniaStore(useSuperAdminAuthStore)?.authUser.access_token;

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

superAdminClient.interceptors.response.use((response) => response, async (error) => {
    if (error.response?.status === 401) {
        piniaStore(useSuperAdminAuthStore)?.removeAuthToken();
        window.location.href = '/super-admin/login';
    }

    throw error;
});

const apiFront = (url, method = 'get', body = {}, options = {}) =>
    formattedRequest(frontClient, method, url, body, options);

const apiAdmin = (url, method = 'get', body = {}, options = {}) =>
    formattedRequest(adminClient, method, url, body, options);

const apiSuperAdmin = (url, method = 'get', body = {}, options = {}) =>
    formattedRequest(superAdminClient, method, url, body, options);

/**
 * Download a file (PDF, CSV, etc.) from an authenticated admin endpoint.
 * Handles the blob response and triggers a browser download.
 *
 * @param {string} url  - relative URL under /api/admin/
 * @param {string} filename - suggested download filename
 * @param {object} params   - query params (GET request)
 */
const downloadAdminFile = async (url, filename, params = {}) => {
    const response = await adminClient.get(url, {
        params,
        responseType: 'blob',
    });
    const blob = new Blob([response.data], {type: response.headers['content-type']});
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = sanitizeDownloadFilename(filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
};

export {apiFront, apiAdmin, apiSuperAdmin, downloadAdminFile};
