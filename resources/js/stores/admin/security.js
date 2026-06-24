import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";

export const useSecurityStore = defineStore('admin-security', {
    actions: {
        getActivity(page = 1) {
            return apiAdmin('profile/security/activity', 'get', {page});
        },
        getDevices() {
            return apiAdmin('profile/security/devices');
        },
        revokeDevice(id) {
            return apiAdmin(`profile/security/devices/${id}`, 'delete');
        },
        revokeOtherDevices() {
            return apiAdmin('profile/security/devices', 'delete');
        },
        deactivateAccount(form) {
            return apiAdmin('profile/security/deactivate', 'post', form);
        },
        deleteAccount(form) {
            return apiAdmin('profile/security/account', 'delete', null, {data: form});
        },
    },
})
