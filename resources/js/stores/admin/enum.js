import {defineStore} from "pinia";
import {apiFront} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useEnumStore = defineStore('enum', {
    state: () => ({
        journalTypes: [],
        tdsCategories: [],
        partyTypes: [],
        crmLeadStatuses: [],
        taskStatuses: [],
        taskPriorities: [],
        followUpChannels: [],
        followUpStatuses: [],
    }),

    actions: {
        getPartyTypes() {
            if (!this.partyTypes.length) {
                return apiFront(`enum/party-types`)
                    .then((res) => {
                        this.partyTypes = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getCrmLeadStatuses() {
            if (!this.crmLeadStatuses.length) {
                return apiFront(`enum/crm-lead-statuses`)
                    .then((res) => {
                        this.crmLeadStatuses = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getTaskStatuses() {
            if (!this.taskStatuses.length) {
                return apiFront(`enum/task-statuses`)
                    .then((res) => {
                        this.taskStatuses = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getTaskPriorities() {
            if (!this.taskPriorities.length) {
                return apiFront(`enum/task-priorities`)
                    .then((res) => {
                        this.taskPriorities = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getFollowUpChannels() {
            if (!this.followUpChannels.length) {
                return apiFront(`enum/follow-up-channels`)
                    .then((res) => {
                        this.followUpChannels = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getFollowUpStatuses() {
            if (!this.followUpStatuses.length) {
                return apiFront(`enum/follow-up-statuses`)
                    .then((res) => {
                        this.followUpStatuses = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getJournalTypes() {
            if (!this.journalTypes.length) {
                return apiFront(`enum/journal-type`)
                    .then((res) => {
                        this.journalTypes = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getTdsCategories() {
            if (!this.tdsCategories.length) {
                return apiFront(`enum/tds-categories`)
                    .then((res) => {
                        this.tdsCategories = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },
    },
})
