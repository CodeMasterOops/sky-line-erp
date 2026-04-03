import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'credit-note';

export const useCreditNoteStore = defineStore('creditNote', {
    state: () => ({
        creditNotes: {
            data: [],
            meta: {},
            loading: false
        },
        creditNote: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getCreditNotes({filter}) {
            this.creditNotes.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.creditNotes.data = res.data.data;
                    this.creditNotes.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.creditNotes.loading = false;
                });
        },
        storeCreditNote(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.creditNotes.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getCreditNote(id) {
            this.creditNote.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.creditNote.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.creditNote.loading = false;
                });
        },
        updateCreditNote(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.creditNotes.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.creditNotes.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveCreditNote(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.creditNotes.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.creditNotes.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteCreditNote(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.creditNotes.data = this.creditNotes.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
