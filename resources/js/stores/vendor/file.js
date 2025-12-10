import {defineStore} from "pinia";
import {apiVendor} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'file'

export const useVendorFileStore = defineStore('vendor-file', {
    state: () => ({
        files: {
            data: [],
            meta: {},
            loading: false
        },
        file: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getFiles({filter}) {
            this.files.loading = true;
            return apiVendor(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
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
            return apiVendor(`${apiUrl}`, 'post', form)
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
            return apiVendor(`${apiUrl}/${id}`)
                .then((res) => {
                    this.file.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.file.loading = false;
                })
        },
        updateFile(id, form) {
            return apiVendor(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.files.data[this.files.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
