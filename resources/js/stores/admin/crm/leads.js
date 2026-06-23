import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';

/**
 * Lead-specific actions that operate on a Party of type=lead.
 * Listing and CRUD of contacts go through the shared party store; this store
 * only owns the pipeline transitions (convert / assign / status).
 */
export const useCrmLeadStore = defineStore('crmLead', {
    actions: {
        convert(partyId) {
            return apiAdmin(`crm/lead/${partyId}/convert`, 'post');
        },
        assign(partyId, userId) {
            return apiAdmin(`crm/lead/${partyId}/assign`, 'post', {
                assigned_to_user_id: userId,
            });
        },
        updateStatus(partyId, payload) {
            return apiAdmin(`crm/lead/${partyId}/status`, 'patch', payload);
        },
    },
});
