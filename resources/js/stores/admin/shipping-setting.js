import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'shipping-setting';

export const useShippingSettingStore = defineStore('shipping-setting', {
    state: () => ({
        shippingSetting: {
            data: [],
            loading: false
        }
    }),
    actions: {
        getShippingSetting() {
            this.shippingSetting.loading = true;
            return apiAdmin(`${apiUrl}`)
                .then((res) => {
                    this.shippingSetting.data = res.data.data;
                })
                .catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.shippingSetting.loading = false;
                });
        },
        updateShippingSetting(form) {
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
