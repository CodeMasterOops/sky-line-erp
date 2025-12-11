import {defineStore} from 'pinia'
import {apiSuperAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'setting';

export const useSuperAdminSettingStore = defineStore('super-admin-setting', {
    state: () => ({
        setting: {
            data: {},
            loading: false
        }
    }),
    actions: {
        getSetting() {
            if (!Object.keys(this.setting.data).length) {
                this.setting.loading = true;
                return apiSuperAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.setting.data = res.data.data;
                    })
                    .catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.setting.loading = false;
                    });
            }
        },
        updateSetting(form) {
            return apiSuperAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.setting.data = res.data.data;
                    return res
                })
                .catch((err) => {
                    throw err;
                });
        }
    }
})
