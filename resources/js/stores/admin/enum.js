import {defineStore} from "pinia";
import {apiAdmin, apiFront} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useEnumStore = defineStore('enum', {
    state: () => ({
        journalTypes: [],
        chargeTypes: [],
    }),

    actions: {
        getJournalTypes() {
            if (!this.journalTypes.length) {
                return apiFront(`enum/journal-type`)
                    .then((res) => {
                        this.journalTypes = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    })
            }
        },

        getChargeTypes() {
            if (this.chargeTypes.length) {
                return Promise.resolve();
            }
            return apiAdmin('enum/charge-types')
                .then((res) => {
                    this.chargeTypes = res.data.data;
                }).catch(showErrors);
        },
    }
})
