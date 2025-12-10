import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'banner'

export const useBannerStore = defineStore('banner', {
    state: () => ({
        banners: {
            data: [],
            loading: false
        },
        banner: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getBanners() {
            if (!this.banners.data.length) {
                this.banners.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.banners.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.banners.loading = false;
                    })
            }
        },
        storeBanner(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.banners.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getBanner(id) {
            this.banner.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.banner.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.banner.loading = false;
                })
        },
        updateBanner(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.banners.data[this.banners.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteBanner(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.banners.data = this.banners.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.banners.data[this.banners.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
