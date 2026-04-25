import {defineStore} from "pinia";
import {apiFront} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useEnumStore = defineStore('enum', {
    state: () => ({
        journalTypes: [],
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
        }
    }
})
