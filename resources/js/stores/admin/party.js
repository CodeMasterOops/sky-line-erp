import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'party';

export const usePartyStore = defineStore('party', {
    state: () => ({
        parties: {
            data: [],
            meta: {},
            loading: false
        },
        party: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getParties({filter}) {
            this.parties.loading = true;
            const q = { ...filter };
            return apiAdmin(`${apiUrl}?${new URLSearchParams(q).toString()}`)
                .then((res) => {
                    this.parties.data = res.data.data;
                    this.parties.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.parties.loading = false;
                });
        },
        storeParty(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.parties.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getParty(id) {
            this.party.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.party.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.party.loading = false;
                });
        },
        updateParty(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.parties.data[this.parties.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteParty(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.parties.data = this.parties.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
