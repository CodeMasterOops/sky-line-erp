import {defineStore} from "pinia";
import {apiFront} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

export const useEnumStore = defineStore('enum', {
    state: () => ({
        orderStatus:  [],
    }),

    actions: {
        getOrderStatus() {
            if (!this.orderStatus.length) {
                return apiFront(`enum/order-status`)
                    .then((res) => {
                        this.orderStatus = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    })
            }
        }
    }
})
