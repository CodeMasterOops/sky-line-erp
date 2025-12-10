import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const roleUrl = 'role'

export const useRoleStore = defineStore('role', {
    state: () => ({
        roles: {
            data: [],
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
        getRoles() {
            if (!this.roles.data.length) {
                this.roles.loading = true;
                return apiAdmin(`${roleUrl}`)
                    .then((res) => {
                        this.roles.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.roles.loading = false;
                    })
            }
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
