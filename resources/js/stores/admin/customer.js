import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'customer'

export const useCustomerStore = defineStore('customer', {
    state: () => ({
        customers: {
            data: [],
            loading: false
        },
        customer: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getCustomers() {
            if (!this.customers.data.length) {
                this.customers.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.customers.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.customers.loading = false;
                    })
            }
        },
        getCustomer(id) {
            this.customer.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.customer.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.customer.loading = false;
                })
        },
        deleteCustomer(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.customers.data = this.customers.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.customers.data[this.customers.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
