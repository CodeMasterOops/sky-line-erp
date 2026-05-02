import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'setting';

export const useSettingStore = defineStore('setting', {
    state: () => ({
        setting: {
            data: {},
            loading: false
        }
    }),
    actions: {
        getSetting(refetch=false) {
            if (!Object.keys(this.setting.data).length || refetch) {
                this.setting.loading = true;
                return apiAdmin(`${apiUrl}`)
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
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    return res
                })
                .catch((err) => {
                    throw err;
                });
        }
    }
})
