import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'tag'

export const useTagStore = defineStore('tag', {
    state: () => ({
        tags: {
            data: [],
            meta: {},
            loading: false
        },
        tag: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getTags({filter}) {
            this.tags.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.tags.data = res.data.data;
                    this.tags.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.tags.loading = false;
                })
        },
        storeTag(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.tags.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getTag(id) {
            this.tag.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.tag.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.tag.loading = false;
                })
        },
        updateTag(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.tags.data[this.tags.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteTag(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.tags.data = this.tags.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
