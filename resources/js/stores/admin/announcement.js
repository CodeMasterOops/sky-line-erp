import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'announcement'

export const useAnnouncementStore = defineStore('announcement', {
    state: () => ({
        announcements: {
            data: [],
            loading: false
        },
        announcement: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getAnnouncements() {
            if (!this.announcements.data.length) {
                this.announcements.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.announcements.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.announcements.loading = false;
                    })
            }
        },
        storeAnnouncement(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.announcements.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getAnnouncement(id) {
            this.announcement.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.announcement.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.announcement.loading = false;
                })
        },
        updateAnnouncement(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.announcements.data[this.announcements.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteAnnouncement(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.announcements.data = this.announcements.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.announcements.data[this.announcements.data.findIndex(d => d.id === id)].is_active = res.data.is_active;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
