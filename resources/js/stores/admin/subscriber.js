import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'subscriber'

export const useSubscriberStore = defineStore('subscriber', {
    state: () => ({
        subscribers: {
            data: [],
            meta: {},
            loading: false
        },
    }),

    actions: {
        getSubscribers({filter}) {
            this.subscribers.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.subscribers.data = res.data.data;
                    this.subscribers.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.subscribers.loading = false;
                })
        },
        deleteSubscriber(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.subscribers.data = this.subscribers.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
