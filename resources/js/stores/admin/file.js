import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'file'

export const useFileStore = defineStore('file', {
    state: () => ({
        files: {
            data: [],
            meta: {},
            loading: false
        },
        file: {
            data: {},
            loading: false
        },
        trashedFiles: {
            data: [],
            meta: {},
            loading: false
        },
    }),

    actions: {
        getFiles({filter}) {
            this.files.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.files.data = res.data.data;
                    this.files.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.files.loading = false;
                })
        },
        storeFile(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    res.data.data.forEach(d => {
                        this.files.data.unshift(d);
                    })
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getFile(id) {
            this.file.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.file.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.file.loading = false;
                })
        },
        updateFile(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.files.data[this.files.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteFile(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.files.data = this.files.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getTrashedFiles({filter}) {
            this.trashedFiles.loading = true;
            return apiAdmin(`${apiUrl}/trashed/list?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.trashedFiles.data = res.data.data;
                    this.trashedFiles.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.trashedFiles.loading = false;
                })
        },
        deleteFilePermanently(id) {
            return apiAdmin(`${apiUrl}/${id}/delete-permanently`, 'delete')
                .then((res) => {
                    this.trashedFiles.data = this.trashedFiles.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
