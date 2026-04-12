import {defineStore} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const apiUrl = 'expense';

export const useExpenseStore = defineStore('expense', {
    state: () => ({
        expenses: {
            data: [],
            meta: {},
            loading: false
        },
        expense: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getExpenses({filter}) {
            this.expenses.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.expenses.data = res.data.data;
                    this.expenses.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.expenses.loading = false;
                });
        },
        storeExpense(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.expenses.data.unshift(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getExpense(id) {
            this.expense.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.expense.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.expense.loading = false;
                });
        },
        updateExpense(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    const index = this.expenses.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.expenses.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        approveExpense(id) {
            return apiAdmin(`${apiUrl}/${id}/approve`, 'post')
                .then((res) => {
                    const index = this.expenses.data.findIndex(d => d.id === id);
                    if (index !== -1) {
                        this.expenses.data[index] = res.data.data;
                    }
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteExpense(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.expenses.data = this.expenses.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        }
    }
});
