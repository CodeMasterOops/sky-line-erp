import {defineStore} from "pinia";
import {apiAdmin} from "@/helpers/api.js";
import showErrors from "@/helpers/showErrors.js";
import Cookies from "js-cookie";

const apiUrl = 'branch';

export const useBranchStore = defineStore('branch', {
    state: () => ({
        selectedBranchId: Cookies.get("selected_branch_id"),
        selectedBranch: null,
        branches: {
            data: [],
            meta: {},
            loading: false
        },
        branch: {
            data: {},
            loading: false
        },
        accessibleBranches: {
            data: [],
            defaultBranchId: null,
            loading: false,
        },
    }),

    actions: {
        getMyBranches() {
            this.accessibleBranches.loading = true;
            return apiAdmin(`${apiUrl}/my-branches`)
                .then((res) => {
                    this.accessibleBranches.data = res.data.data;
                    this.accessibleBranches.defaultBranchId = res.data.default_branch_id ?? null;
                }).catch(showErrors).finally(() => {
                    this.accessibleBranches.loading = false;
                });
        },
        autoSelectDefault() {
            if (this.selectedBranchId) {
                return;
            }
            const defaultId = this.accessibleBranches.defaultBranchId;
            const branches = this.accessibleBranches.data;
            const branch = defaultId
                ? branches.find(b => b.id === defaultId)
                : branches.length === 1 ? branches[0] : null;
            if (branch) {
                this.setSelectedBranch(branch);
            }
        },
        getBranches({ filter } = {}) {
            const params = {
                page: filter?.page ?? 1,
                limit: filter?.limit ?? 1000,
            };
            this.branches.loading = true;
            return apiAdmin(`${apiUrl}?${new URLSearchParams(params)}`)
                .then((res) => {
                    this.branches.data = res.data.data;
                    this.branches.meta = res.data.meta ?? {};
                }).catch(showErrors).finally(() => {
                    this.branches.loading = false;
                });
        },
        storeBranch(form) {
            return apiAdmin(`${apiUrl}`, 'post', form)
                .then((res) => {
                    this.branches.data.push(res.data.data);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        getBranch(id) {
            this.branch.loading = true;
            return apiAdmin(`${apiUrl}/${id}`)
                .then((res) => {
                    this.branch.data = res.data.data;
                }).catch((err) => {
                    showErrors(err);
                }).finally(() => {
                    this.branch.loading = false;
                });
        },
        updateBranch(id, form) {
            return apiAdmin(`${apiUrl}/${id}`, 'put', form)
                .then((res) => {
                    this.branches.data[this.branches.data.findIndex(d => d.id === id)] = res.data.data;
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        deleteBranch(id) {
            return apiAdmin(`${apiUrl}/${id}`, 'delete')
                .then((res) => {
                    this.branches.data = this.branches.data.filter(d => d.id !== id);
                    return res;
                }).catch((err) => {
                    throw err;
                });
        },
        loadSelectedBranch() {
            const branchId = this.selectedBranchId;
            if (!branchId) {
                this.selectedBranch = null;
                return Promise.resolve(null);
            }
            return apiAdmin(`${apiUrl}/${branchId}`)
                .then((res) => {
                    this.selectedBranch = res.data.data;
                    return res.data.data;
                }).catch((err) => {
                    this.clearSelectedBranch();
                    throw err;
                });
        },
        setBranchId(branchId) {
            this.selectedBranchId = branchId;
            Cookies.set("selected_branch_id", branchId, {
                expires: 1,
                secure: false,
                sameSite: "Strict",
                path: '/',
            });
        },
        setSelectedBranch(branch) {
            this.setBranchId(branch?.id ?? '');
            this.selectedBranch = branch || null;
        },
        clearSelectedBranch() {
            this.selectedBranchId = null;
            this.selectedBranch = null;
            Cookies.remove("selected_branch_id", {
                secure: false,
                sameSite: "Strict",
                path: '/',
            });
        },
        async ensureSelectedBranchLoaded() {
            if (!this.selectedBranchId) {
                this.selectedBranch = null;
                return null;
            }

            if (String(this.selectedBranch?.id) === String(this.selectedBranchId)) {
                return this.selectedBranch;
            }

            try {
                return await this.loadSelectedBranch();
            } catch (err) {
                return null;
            }
        },
    }
});
