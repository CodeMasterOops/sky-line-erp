import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const roleUrl = 'role'

export const useRoleStore = defineStore('role', {
    state: () => ({
        roles: {
            data: [],
            meta: {},
            loading: false
        },
        role: {
            data: {},
            loading: false
        },
        permissions: {
            data: [],
            permissionIds: []
        }
    }),

    actions: {
        getPermissions() {
            this.permissions.loading = true;
            return apiAdmin(`permission`)
                .then((res) => {
                    this.permissions.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.permissions.loading = false;
                })
        },
        getRoles({ filter } = {}) {
            const params = {
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
            };
            this.roles.loading = true;
            return apiAdmin(`${roleUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.roles.data = res.data.data;
                    this.roles.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.roles.loading = false;
                });
        },
        storeRole(form) {
            return apiAdmin(`${roleUrl}`, 'post', form)
                .then((res) => {
                    this.roles.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getRole(id) {
            this.role.loading = true;
            return apiAdmin(`${roleUrl}/${id}`)
                .then((res) => {
                    this.role.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.role.loading = false;
                })
        },
        updateRole(id, form) {
            return apiAdmin(`${roleUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.roles.data[this.roles.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteRole(id) {
            return apiAdmin(`${roleUrl}/${id}`, 'delete')
                .then((res) => {
                    this.roles.data = this.roles.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
