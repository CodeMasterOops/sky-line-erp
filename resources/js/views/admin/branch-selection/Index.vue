<template>
    <div class="branch-select-page">
        <div class="branch-select-shell">
            <div class="branch-select-card card border-0 shadow-sm">
                <div class="card-body p-4 p-lg-5">
                    <div class="branch-select-head">
                        <span class="branch-select-icon">
                            <i class="ti ti-building-store"></i>
                        </span>
                        <div>
                            <h2 class="mb-2">Select Branch</h2>
                            <p class="text-muted mb-0">
                                Choose the branch context to continue using branch-wise accounting features.
                            </p>
                        </div>
                    </div>

                    <div v-if="branches.loading" class="py-5 text-center text-muted">
                        Loading branches...
                    </div>

                    <div v-else-if="branches.data.length" class="row g-3 mt-1">
                        <div class="col-md-6 col-xl-4" v-for="branch in branches.data" :key="branch.id">
                            <button
                                type="button"
                                class="branch-option"
                                :class="{ active: String(selectedBranchId) === String(branch.id) }"
                                :disabled="submitting"
                                @click="selectBranch(branch)"
                            >
                                <div class="branch-option-top">
                                    <div>
                                        <div class="branch-option-title">
                                            {{ branch.name }}
                                            <span v-if="branch.is_head_office"
                                                  class="badge bg-primary ms-2">Head Office</span>
                                        </div>
                                        <div class="branch-option-code">{{ branch.code }}</div>
                                    </div>
                                    <span v-if="String(selectedBranchId) === String(branch.id)"
                                          class="branch-option-check">
                                        <i class="ti ti-check"></i>
                                    </span>
                                </div>
                                <div class="branch-option-meta">
                                    <span>{{ branch.phone || 'No phone' }}</span>
                                    <span>{{ branch.address || 'No address' }}</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div v-else class="branch-empty text-center">
                        <h5 class="mb-2">No branches available</h5>
                        <p class="text-muted mb-3">
                            Create a branch first before you continue with branch-based transactions and reports.
                        </p>
                        <router-link
                            v-if="canOpenBranchSettings"
                            :to="{ name: 'admin.branch-list' }"
                            class="btn btn-primary"
                        >
                            Manage Branches
                        </router-link>
                        <p v-else class="text-muted mb-0">
                            You do not have access to create or manage branches. Contact an administrator to create a branch first.
                        </p>
                    </div>

                    <div class="branch-select-footer mt-4">
                        <button
                            v-if="selectedBranchId"
                            type="button"
                            class="btn btn-light"
                            :disabled="submitting"
                            @click="clearBranch"
                        >
                            Clear Selection
                        </button>
                        <router-link
                            v-if="selectedBranchId && redirectPath"
                            :to="redirectPath"
                            class="btn btn-outline-secondary"
                        >
                            Cancel
                        </router-link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {computed, onMounted, ref} from 'vue';
import {storeToRefs} from 'pinia';
import {useRoute, useRouter} from 'vue-router';
import showErrors from '@/helpers/showErrors';
import {toast} from '@/helpers/toast';
import {useBranchStore} from '@/stores/admin/settings/branch.js';
import {getAdminRoutePermission} from '@/router/adminRoutePermissions';
import {satisfiesAdminRoutePermission} from '@/helpers/checkPermission';

const branchStore = useBranchStore();
const router = useRouter();
const route = useRoute();
const submitting = ref(false);

const {branches, selectedBranchId} = storeToRefs(branchStore);

const redirectPath = computed(() => {
    const redirect = route.query.redirect;
    return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/admin/dashboard';
});

const canOpenBranchSettings = computed(() =>
    satisfiesAdminRoutePermission(getAdminRoutePermission('admin.branch-list'))
);

onMounted(async () => {
    await branchStore.getBranches(true);
    await branchStore.ensureSelectedBranchLoaded();

    if (!branches.value.data.length && canOpenBranchSettings.value) {
        await router.replace({ name: 'admin.branch-list' });
    }
});

const selectBranch = async (branch) => {
    submitting.value = true;
    try {
        branchStore.setSelectedBranch(branch);
        toast(200, 'Branch selected successfully');
        window.location.href = redirectPath.value;
    } catch (e) {
        showErrors(e);
    } finally {
        submitting.value = false;
    }
};

const clearBranch = () => {
    branchStore.clearSelectedBranch();
};
</script>

<style scoped>
.branch-select-page {
    min-height: calc(100vh - 70px);
    padding: 32px 16px;
    background: radial-gradient(circle at top left, rgba(33, 150, 243, 0.12), transparent 28%),
    linear-gradient(180deg, #f6f9fc 0%, #eef3f7 100%);
}

.branch-select-shell {
    max-width: 1100px;
    margin: 0 auto;
}

.branch-select-card {
    border-radius: 24px;
}

.branch-select-head {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    margin-bottom: 24px;
}

.branch-select-icon {
    width: 56px;
    height: 56px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0d6efd, #2a9df4);
    color: #fff;
    font-size: 24px;
    flex-shrink: 0;
}

.branch-option {
    width: 100%;
    border: 1px solid #dbe5ef;
    border-radius: 18px;
    background: #fff;
    padding: 18px;
    text-align: left;
    transition: 0.2s ease;
    min-height: 160px;
}

.branch-option:hover,
.branch-option.active {
    border-color: #0d6efd;
    box-shadow: 0 16px 40px rgba(13, 110, 253, 0.12);
    transform: translateY(-1px);
}

.branch-option-top {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.branch-option-title {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}

.branch-option-code {
    font-size: 13px;
    color: #64748b;
    margin-top: 4px;
}

.branch-option-check {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #0d6efd;
    color: #fff;
    flex-shrink: 0;
}

.branch-option-meta {
    display: grid;
    gap: 8px;
    font-size: 14px;
    color: #475569;
}

.branch-empty {
    padding: 48px 16px;
    border: 1px dashed #cbd5e1;
    border-radius: 18px;
    background: #f8fafc;
}

.branch-select-footer {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

@media (max-width: 767.98px) {
    .branch-select-head {
        flex-direction: column;
    }

    .branch-select-footer {
        justify-content: stretch;
        flex-direction: column;
    }
}
</style>
