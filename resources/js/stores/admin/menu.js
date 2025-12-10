import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const menuUrl = 'menu'

export const useMenuStore = defineStore('menu', {
    state: () => ({
        menu_type:'header',
        menus: {
            data: [],
            loading: false
        },
        menu: {
            data: {},
            loading: false
        },
    }),

    actions: {
        getMenus(menu_type='header') {
            this.menus.loading = true;
            return apiAdmin(`${menuUrl}?menu_type=${menu_type}`)
                .then((res) => {
                    this.menus.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.menus.loading = false;
                })
        },
        storeMenu(form) {
            return apiAdmin(`${menuUrl}`, 'post', form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getMenu(id) {
            this.menu.loading = true;
            return apiAdmin(`${menuUrl}/${id}`)
                .then((res) => {
                    this.menu.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.menu.loading = false;
                })
        },
        updateMenu(id, form) {
            return apiAdmin(`${menuUrl}/${id}`, 'put', form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteMenu(id) {
            return apiAdmin(`${menuUrl}/${id}`, 'delete')
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${menuUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        }
    }
})
