import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'crm/note';

export const useCrmNoteStore = defineStore('crmNote', {
    state: () => ({
        notes: {
            data: [],
            meta: {},
            loading: false,
        },
    }),

    actions: {
        getNotes(partyId) {
            this.notes.loading = true;
            return apiAdmin(`${apiUrl}?party_id=${partyId}&limit=100`)
                .then((res) => {
                    this.notes.data = res.data.data;
                    this.notes.meta = res.data.meta ?? {};
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.notes.loading = false;
                });
        },
        storeNote(partyId, body) {
            return apiAdmin(apiUrl, 'post', { party_id: partyId, body });
        },
        deleteNote(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete');
        },
    },
});
