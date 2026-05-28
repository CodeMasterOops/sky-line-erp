import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'attribute'

export const useAttributeStore = defineStore('attribute', {
    state: () => ({
        attributes: {
            data: [],
            meta: {},
            loading: false
        },
        attribute: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getAttributes({ filter } = {}) {
            const params = {
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
            };
            this.attributes.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.attributes.data = res.data.data;
                    this.attributes.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.attributes.loading = false;
                });
        },
        storeAttribute(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.attributes.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getAttribute(id) {
            this.attribute.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.attribute.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.attribute.loading = false;
                })
        },
        updateAttribute(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.attributes.data[this.attributes.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteAttribute(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.attributes.data = this.attributes.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.attributes.data[this.attributes.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
