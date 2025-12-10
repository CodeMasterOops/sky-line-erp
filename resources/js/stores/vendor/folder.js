import {defineStore} from "pinia";
import {apiVendor} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'folder'

export const useVendorFolderStore = defineStore('vendor-folder', {
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
            return apiVendor(`${apiUrl}?folder_id=${folder_id}`)
                .then((res) => {
                    this.folders.data = res.data.data;
                    this.folders.self = res.data.self;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.folders.loading = false;
                })
        },
        getFolder(id) {
            this.folder.loading = true;
            return apiVendor(`${apiUrl}/${id}`)
                .then((res) => {
                    this.folder.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.folder.loading = false;
                })
        }
    }
})
