import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";
import {useAdminAuthStore} from "@/stores/admin/auth";
import {useDatePreferenceStore} from "@/stores/admin/datePreference";
import {usePinnedLinksStore} from "@/stores/admin/pinnedLinks";

export const useProfileStore = defineStore('admin-profile', {
    state: () => {
        let profileData = {};
        try {
            const authUser = localStorage.getItem('admin_user');
            if (authUser) {
                profileData = JSON.parse(authUser);
            }
        } catch {
            localStorage.removeItem('admin_user');
        }
        return {
            profile: {
                data: profileData,
                loading: false
            },
        }
    },
    actions: {
        getProfile() {
            if (this.profile.loading) {
                return Promise.resolve();
            }
            this.profile.loading = true;
            return apiAdmin(`profile`)
                .then((res) => {
                    this.profile.data = res.data.data;
                    this.setAuthUser(res.data.data);
                    useAdminAuthStore().setPermissions(res.data.data.user_type, res.data.permissions)
                })
                .catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.profile.loading = false;
                });
        },
        updateProfile(form) {
            return apiAdmin(`profile/update`, 'post', form)
                .then((res) => {
                    this.setAuthUser(res.data.data);
                    return res
                })
                .catch((err) => {
                    throw err;
                });
        },
        changePassword(form) {
            return apiAdmin(`profile/change-password`, 'put', form)
                .then((res) => {
                    // All previous tokens were revoked server-side; update the stored token.
                    useAdminAuthStore().setAuthToken(res.data.access_token, res.data.expires_at);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        setAuthUser(user) {
            this.profile.data = user;
            localStorage.setItem('admin_user', JSON.stringify(user));
            if (user?.date_mode) {
                useDatePreferenceStore().applyMode(user.date_mode);
            }
            if (user) {
                usePinnedLinksStore().applyLinks(user.dashboard_pinned_links ?? []);
            }
        },
    }
})
