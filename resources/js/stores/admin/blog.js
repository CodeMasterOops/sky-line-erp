import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api";
import showErrors from "@/helpers/showErrors";

const apiUrl = 'blog'

export const useBlogStore = defineStore('blog', {
    state: () => ({
        allBlogs: {
            data: [],
            loading: false
        },
        blogs: {
            data: [],
            meta: {},
            loading: false
        },
        blog: {
            data: {},
            loading: false
        }
    }),

    actions: {
        getAllBlogs() {
            if(!this.allBlogs.data.length){
                this.allBlogs.loading = true;
                return apiAdmin(`${apiUrl}/list/all`)
                    .then((res) => {
                        this.allBlogs.data = res.data.data;
                    }).catch((err) => {
                        showErrors(err);
                    }).finally(() => {
                        this.allBlogs.loading = false;
                    })
            }
        },
        getBlogs({filter}) {
            this.blogs.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(filter).toString()}`)
                .then((res) => {
                    this.blogs.data = res.data.data;
                    this.blogs.meta = res.data.meta;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.blogs.loading = false;
                })
        },
        storeBlog(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.blogs.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        getBlog(id) {
            this.blog.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.blog.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.blog.loading = false;
                })
        },
        updateBlog(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.blogs.data[this.blogs.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        deleteBlog(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.blogs.data = this.blogs.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
        updateStatus(id) {
            return apiAdmin(`${apiUrl}/${id}/update-status`, 'put')
                .then((res) => {
                    this.blogs.data[this.blogs.data.findIndex(d => d.id === id)].status = res.data.status;
                    return res;
                }).catch((err) => {
                    throw err;
                })
        },
    }
})
