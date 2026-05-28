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
        getAllNotifications({filter} = {}) {
            this.allNotifications.loading = true;
            const params = new URLSearchParams();
            if (filter?.page) {
                params.set('page', String(filter.page));
            }
            if (filter?.limit) {
                params.set('limit', String(filter.limit));
            }
            const query = params.toString();

            return apiAdmin(`${apiUrl}/all${query ? `?${query}` : ''}`)
                .then((res) => {
                    this.allNotifications.data = res.data.data;
                    this.allNotifications.meta = res.data.meta ?? {};
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
