import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'collection'

export const useCollectionStore = defineStore('collection', {
    state: () => ({
        collections: {
            data: [],
            loading: false
        },
        collection: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getCollections() {
            if (!this.collections.data.length) {
                this.collections.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.collections.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.collections.loading = false;
                    })
            }
        },
        storeCollection(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.collections.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getCollection(id) {
            this.collection.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.collection.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.collection.loading = false;
                })
        },
        updateCollection(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.collections.data[this.collections.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteCollection(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.collections.data = this.collections.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.collections.data[this.collections.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
