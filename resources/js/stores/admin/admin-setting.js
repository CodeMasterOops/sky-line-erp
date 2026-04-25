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
        currentFiscalYear: {
            data: {},
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
        },
        getCurrentFiscalYear(refetch = false) {
            if (!Object.keys(this.currentFiscalYear.data).length || refetch) {
                this.currentFiscalYear.loading = true;
                return apiAdmin(`${apiUrl}/current-fiscal-year`)
                    .then((res) => {
                        this.currentFiscalYear.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.currentFiscalYear.loading = false;
                    })
            }
        }
    }
})
