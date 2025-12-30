import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'admin-setting'

export const useAdminSettingStore = defineStore('admin-setting', {
    state: () => ({
        fiscalYears: {
            data: [],
            loading: false
        },
    }),

    actions: {
        getFiscalYears() {
            if (!this.fiscalYears.data.length) {
                this.fiscalYears.loading = true;
                return apiAdmin(`${apiUrl}/fiscal-year`)
                    .then((res) => {
                        this.fiscalYears.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.fiscalYears.loading = false;
                    })
            }
        }
    }
})
