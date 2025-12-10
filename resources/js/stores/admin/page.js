import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'page'

export const usePageStore = defineStore('page', {
    state: () => ({
        pages: {
            data: [],
            meta: {},
            loading: false
        },
        page: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getPages({filter}) {
            this.pages.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.pages.data = res.data.data;
                    this.pages.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.pages.loading = false;
                })
        },
        storePage(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.pages.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getPage(id) {
            this.page.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.page.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.page.loading = false;
                })
        },
        updatePage(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.pages.data[this.pages.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deletePage(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.pages.data = this.pages.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.pages.data[this.pages.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
