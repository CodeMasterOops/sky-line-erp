import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'stock'

export const useStockStore = defineStore('stock', {
    state: () => ({
        stocks: {
            data: [],
            meta: {},
            loading: false
        },
        stockHistories:{
            variant:{},
            data:[],
            meta:{},
            loading:false
        }
    }),

    actions: {
        getStockList({filter}) {
            this.stocks.loading = true;
            return apiAdmin(`${apiUrl}/list?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.stocks.data = res.data.data;
                    this.stocks.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.stocks.loading = false;
                })
        },
        updateStock(id, form) {
            return apiAdmin(`${apiUrl}/${id}/update`, 'post', form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getStockHistories({variant_id,filter}) {
            this.stockHistories.loading = true;
            return apiAdmin(`${apiUrl}/${variant_id}/history?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.stockHistories.variant = res.data.variant;
                    this.stockHistories.data = res.data.histories.data;
                    this.stockHistories.meta = res.data.histories.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.stockHistories.loading = false;
                })
        },
    }
})
