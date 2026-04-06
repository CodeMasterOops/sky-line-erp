import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'debit-note';

export const useDebitNoteStore = defineStore('debitNote', {
    state: () => ({
        debitNotes: {
            data: [],
            meta: {},
            loading: false
        },
        debitNote: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getDebitNotes({filter}) {
            this.debitNotes.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.debitNotes.data = res.data.data;
                    this.debitNotes.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.debitNotes.loading = false;
                });
        },
        storeDebitNote(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.debitNotes.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getDebitNote(id) {
            this.debitNote.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.debitNote.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.debitNote.loading = false;
                });
        },
        updateDebitNote(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.debitNotes.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.debitNotes.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveDebitNote(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.debitNotes.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.debitNotes.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteDebitNote(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.debitNotes.data = this.debitNotes.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
