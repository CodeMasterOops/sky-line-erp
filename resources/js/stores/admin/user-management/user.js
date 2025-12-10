import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const userUrl = 'user'

export const useUserStore = defineStore('user', {
    state: () => ({
        users: {
            data: [],
            loading: false
        },
        user: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getUsers() {
            if (!this.users.data.length) {
                this.users.loading = true;
                return apiAdmin(`${userUrl}`)
                    .then((res) => {
                        this.users.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.users.loading = false;
                    })
            }
        },
        storeUser(form) {
            return apiAdmin(`${userUrl}`, 'post', form)
                .then((res) => {
                    this.users.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getUser(id) {
            this.user.loading = true;
            return apiAdmin(`${userUrl}/${id}`)
                .then((res) => {
                    this.user.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.user.loading = false;
                })
        },
        updateUser(id, form) {
            return apiAdmin(`${userUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.users.data[this.users.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteUser(id) {
            return apiAdmin(`${userUrl}/${id}`, 'delete')
                .then((res) => {
                    this.users.data = this.users.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${userUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.users.data[this.users.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
