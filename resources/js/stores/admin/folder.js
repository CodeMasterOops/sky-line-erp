import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'folder'

export const useFolderStore = defineStore('folder', {
    state: () => ({
        folders: {
            data: [],
            self: null,
            loading: false
        },
        folder: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getFolders(folder_id = '') {
            this.folders.loading = true;
            return apiAdmin(`${apiUrl}?folder_id=${folder_id}`)
                .then((res) => {
                    this.folders.data = res.data.data;
                    this.folders.self = res.data.self;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.folders.loading = false;
                })
        },
        storeFolder(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.folders.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getFolder(id) {
            this.folder.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.folder.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.folder.loading = false;
                })
        },
        updateFolder(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.folders.data[this.folders.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteFolder(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.folders.data = this.folders.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
