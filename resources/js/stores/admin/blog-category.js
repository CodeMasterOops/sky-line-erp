import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'blog-category'

export const useBlogCategoryStore = defineStore('blogCategory', {
    state: () => ({
        blogCategories: {
            data: [],
            loading: false
        },
        blogCategory: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getBlogCategories() {
            if (!this.blogCategories.data.length) {
                this.blogCategories.loading = true;
                return apiAdmin(`${apiUrl}`)
                    .then((res) => {
                        this.blogCategories.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.blogCategories.loading = false;
                    })
            }
        },
        storeBlogCategory(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.blogCategories.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getBlogCategory(id) {
            this.blogCategory.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.blogCategory.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.blogCategory.loading = false;
                })
        },
        updateBlogCategory(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.blogCategories.data[this.blogCategories.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteBlogCategory(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.blogCategories.data = this.blogCategories.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.blogCategories.data[this.blogCategories.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
