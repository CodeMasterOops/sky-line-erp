import {defineStore} from 'pinia'
import {apiVendor} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'shipping-setting';

export const useVendorShippingSettingStore = defineStore('vendor-shipping-setting', {
    state: () => ({
        shippingSetting: {
            data: [],
            loading: false
        }
    }),
    actions: {
        getShippingSetting() {
            this.shippingSetting.loading = true;
            return apiVendor(`${apiUrl}`)
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
            return apiVendor(`${apiUrl}`, 'post', form)
                .then((res) => {
                    return res
                })
                .catch((err) => {
                    throw err;
                });
        }
    }
})
