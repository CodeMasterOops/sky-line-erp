import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'faq'

export const useFaqStore = defineStore('faq', {
    state: () => ({
        faqs: {
            data: [],
            loading: false
        },
        faq: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getFaqs() {
            if (!this.faqs.data.length) {
                this.faqs.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.faqs.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.faqs.loading = false;
                    })
            }
        },
        storeFaq(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.faqs.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getFaq(id) {
            this.faq.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.faq.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.faq.loading = false;
                })
        },
        updateFaq(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.faqs.data[this.faqs.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteFaq(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.faqs.data = this.faqs.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.faqs.data[this.faqs.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
