import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useEnumStore = defineStore('enum', {
    state: () => ({
        genders: [],
        bloodGroups: [],
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
        getGenders() {
            if (!this.genders.length) {
                return apiAdmin(`enum/genders`)
                    .then((res) => {
                        this.genders = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getBloodGroups() {
            if (!this.bloodGroups.length) {
                return apiAdmin(`enum/blood-groups`)
                    .then((res) => {
                        this.bloodGroups = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getPartyTypes() {
            if (!this.partyTypes.length) {
                return apiAdmin(`enum/party-types`)
                    .then((res) => {
                        this.partyTypes = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getCrmLeadStatuses() {
            if (!this.crmLeadStatuses.length) {
                return apiAdmin(`enum/crm-lead-statuses`)
                    .then((res) => {
                        this.crmLeadStatuses = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getTaskStatuses() {
            if (!this.taskStatuses.length) {
                return apiAdmin(`enum/task-statuses`)
                    .then((res) => {
                        this.taskStatuses = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getTaskPriorities() {
            if (!this.taskPriorities.length) {
                return apiAdmin(`enum/task-priorities`)
                    .then((res) => {
                        this.taskPriorities = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getFollowUpChannels() {
            if (!this.followUpChannels.length) {
                return apiAdmin(`enum/follow-up-channels`)
                    .then((res) => {
                        this.followUpChannels = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getFollowUpStatuses() {
            if (!this.followUpStatuses.length) {
                return apiAdmin(`enum/follow-up-statuses`)
                    .then((res) => {
                        this.followUpStatuses = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getJournalTypes() {
            if (!this.journalTypes.length) {
                return apiAdmin(`enum/journal-type`)
                    .then((res) => {
                        this.journalTypes = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },

        getTdsCategories() {
            if (!this.tdsCategories.length) {
                return apiAdmin(`enum/tds-categories`)
                    .then((res) => {
                        this.tdsCategories = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    });
            }
        },
    },
})
