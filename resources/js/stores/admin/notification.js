import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'notification';

export const useAdminNotificationStore = defineStore('admin-notification', {
    state: () => ({
        allNotifications: {
            data: [],
            meta: {},
            loading: false
        },
        unreadNotifications: {
            data: [],
            loading: false
        },
    }),
    actions: {
        getAllNotifications() {
            this.allNotifications.loading = true;
            return apiAdmin(`${apiUrl}/all`)
                .then((res) => {
                    this.allNotifications.data = res.data.data;
                })
                .catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.allNotifications.loading = false;
                });
        },
        getUnreadNotifications() {
            this.unreadNotifications.loading = true;
            return apiAdmin(`${apiUrl}/unread`)
                .then((res) => {
                    this.unreadNotifications.data = res.data.data;
                })
                .catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.unreadNotifications.loading = false;
                });
        },
        markAsRead(id = '') {
            return apiAdmin(`${apiUrl}/mark-as-read/${id}`, 'post')
                .then((res) => {
                    this.unreadNotifications.data = id ? this.unreadNotifications.data.filter(n => n.id !== id) : [];
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
    }
})
