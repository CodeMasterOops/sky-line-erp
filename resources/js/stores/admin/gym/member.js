import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'gym/member';

export const useGymMemberStore = defineStore('gymMember', {
    state: () => ({
        members: {
            data: [],
            meta: {},
            loading: false,
        },
        member: {
            data: null,
            loading: false,
        },
        nextCode: '',
    }),

    actions: {
        getMembers({ filter = {} } = {}) {
            this.members.loading = true;
            const query = new URLSearchParams(filter).toString();

            return apiAdmin(`${apiUrl}${query ? `?${query}` : ''}`)
                .then((res) => {
                    this.members.data = res.data.data;
                    this.members.meta = res.data.meta ?? {};
                })
                .catch(showErrors)
                .finally(() => {
                    this.members.loading = false;
                });
        },

        getMember(id) {
            this.member.loading = true;

            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.member.data = res.data.data;
                })
                .catch(showErrors)
                .finally(() => {
                    this.member.loading = false;
                });
        },

        getNextCode() {
            return apiAdmin(`${apiUrl}/next-code`)
                .then((res) => {
                    this.nextCode = res.data.data.member_code;
                    return this.nextCode;
                })
                .catch(() => {});
        },

        storeMember(form) {
            return apiAdmin(apiUrl, 'post', form);
        },

        updateMember(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form);
        },

        /** Photo goes up on its own so the profile form stays a plain JSON POST. */
        uploadPhoto(id, file) {
            const payload = new FormData();
            payload.append('photo', file);

            return apiAdmin(`${apiUrl}/${id}/photo`, 'post', payload);
        },

        deleteMember(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete').then((res) => {
                this.members.data = this.members.data.filter((m) => m.id !== id);
                return res;
            });
        },
    },
});
