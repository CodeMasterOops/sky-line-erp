import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'author'

export const useAuthorStore = defineStore('author', {
    state: () => ({
        authors: {
            data: [],
            loading: false
        },
        author: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getAuthors() {
            if (!this.authors.data.length) {
                this.authors.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.authors.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.authors.loading = false;
                    })
            }
        },
        storeAuthor(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.authors.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getAuthor(id) {
            this.author.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.author.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.author.loading = false;
                })
        },
        updateAuthor(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.authors.data[this.authors.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteAuthor(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.authors.data = this.authors.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.authors.data[this.authors.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
