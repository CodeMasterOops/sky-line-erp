import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";

export const useCacheSettingStore = defineStore('cacheSetting', {
    actions: {
        clearSystemCache() {
            return apiAdmin('cache/clear-system-cache', 'post')
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        clearCloudflareApiCache() {
            return apiAdmin('cache/clear-cloudflare-api-cache', 'post')
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        clearCloudflareCache(form) {
            return apiAdmin('cache/clear-cloudflare-cache', 'post',form)
                .then((res) => {
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
