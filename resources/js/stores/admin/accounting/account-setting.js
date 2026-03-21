import { defineStore } from "pinia";
import { apiAdmin } from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = "account-setting";

export const useAccountSettingStore = defineStore("account-setting", {
    state: () => ({
        accountSetting: {
            data: {},
            loading: false,
        },
    }),
    actions: {
        getAccountSetting(refetch = false) {
            if (!Object.keys(this.accountSetting.data).length || refetch) {
                this.accountSetting.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.accountSetting.data = res.data.data;
                    })
                    .catch((err) => {
                        showErrors(err);
                    })
                    .finally(() => {
                        this.accountSetting.loading = false;
                    });
            }
        },
        updateAccountSetting(form) {
            return apiAdmin(`${apiUrl}`, "post", form)
                .then((res) => res)
                .catch((err) => {
                    throw err;
                });
        },
    },
});
