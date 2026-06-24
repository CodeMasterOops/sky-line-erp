<template>
    <main class="main-wrapper">
        <div class="account-content">
            <div class="login-wrapper login-new">
                <div class="row w-100 justify-content-center">
                    <div class="col-lg-8 col-xl-7">
                        <div class="login-content user-login">
                            <div class="login-logo">
                                <img :src="appLogo" alt="Sky ERP" />
                                <router-link :to="{ name: 'admin.dashboard' }" class="login-logo logo-white">
                                    <img :src="appLogoWhite" alt="Sky ERP" />
                                </router-link>
                            </div>

                            <div class="card shadow-sm">
                                <div class="card-body p-4 p-lg-5">
                                    <div class="login-userheading mb-4">
                                        <h3>Choose your branch</h3>
                                        <h4>
                                            Select the branch you want to work in. Reports and transactions use this
                                            context across the app.
                                        </h4>
                                    </div>

                                    <div v-if="accessibleBranches.loading" class="py-5 text-center text-muted">
                                        <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Loading branches...
                                    </div>

                                    <template v-else-if="accessibleBranches.data.length">
                                        <div class="row g-3">
                                            <div
                                                v-for="branch in accessibleBranches.data"
                                                :key="branch.id"
                                                class="col-md-6"
                                            >
                                                <button
                                                    type="button"
                                                    class="branch-option w-100"
                                                    :class="{ active: String(selectedBranchId) === String(branch.id) }"
                                                    :disabled="submitting"
                                                    @click="selectBranch(branch)"
                                                >
                                                    <div class="branch-option-top">
                                                        <div
                                                            class="branch-option-icon"
                                                            :class="{ 'branch-option-icon--active': String(selectedBranchId) === String(branch.id) }"
                                                        >
                                                            <i class="ti ti-building-store"></i>
                                                        </div>
                                                        <div class="flex-grow-1 text-start">
                                                            <div class="branch-option-title">
                                                                {{ branch.name }}
                                                                <span
                                                                    v-if="branch.is_head_office"
                                                                    class="badge bg-primary-subtle text-primary ms-2"
                                                                >Head Office</span>
                                                            </div>
                                                            <div class="branch-option-code">{{ branch.code }}</div>
                                                        </div>
                                                        <span
                                                            v-if="String(selectedBranchId) === String(branch.id)"
                                                            class="branch-option-check"
                                                        >
                                                            <i class="ti ti-check"></i>
                                                        </span>
                                                    </div>
                                                    <div class="branch-option-meta">
                                                        <span v-if="branch.phone">
                                                            <i class="ti ti-phone me-1"></i>{{ branch.phone }}
                                                        </span>
                                                        <span v-if="branch.address">
                                                            <i class="ti ti-map-pin me-1"></i>{{ branch.address }}
                                                        </span>
                                                        <span v-if="!branch.phone && !branch.address" class="text-muted">
                                                            No contact details
                                                        </span>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            v-if="selectedBranchId"
                                            class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 pt-3 border-top"
                                        >
                                            <button
                                                type="button"
                                                class="btn btn-link text-muted px-0"
                                                :disabled="submitting"
                                                @click="clearBranch"
                                            >
                                                Clear selection
                                            </button>
                                            <div class="d-flex gap-2 ms-auto">
                                                <router-link
                                                    v-if="redirectPath"
                                                    :to="redirectPath"
                                                    class="btn btn-outline-secondary"
                                                >
                                                    Cancel
                                                </router-link>
                                                <button
                                                    type="button"
                                                    class="btn btn-primary px-4"
                                                    :disabled="submitting || !selectedBranchId"
                                                    @click="continueWithSelection"
                                                >
                                                    <span
                                                        v-if="submitting"
                                                        class="spinner-border spinner-border-sm me-1"
                                                    ></span>
                                                    Continue
                                                    <i class="ti ti-arrow-right ms-1"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <div v-else class="text-center py-4">
                                        <div
                                            class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3"
                                            style="width: 72px; height: 72px;"
                                        >
                                            <i class="ti ti-building-off text-muted" style="font-size: 32px;"></i>
                                        </div>
                                        <h5 class="fw-bold mb-2">No branches available</h5>
                                        <p class="text-muted mb-3">
                                            Create a branch before you continue with branch-based transactions and
                                            reports.
                                        </p>
                                        <router-link
                                            v-if="canOpenBranchSettings"
                                            :to="{ name: 'admin.branch-list' }"
                                            class="btn btn-primary"
                                        >
                                            Manage Branches
                                        </router-link>
                                        <p v-else class="text-muted small mb-0">
                                            You do not have permission to manage branches. Contact an administrator.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="my-4 text-center copyright-text">
                                <p class="text-muted small">Copyright &copy; {{ currentYear }} Sky ERP Pro</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute, useRouter } from 'vue-router';
import showErrors from '@/helpers/showErrors';
import { toast } from '@/helpers/toast';
import { useBranchStore } from '@/stores/admin/settings/branch.js';
import { useAdminAuthStore } from '@/stores/admin/auth.js';
import { getAdminRoutePermission } from '@/router/adminRoutePermissions';
import { satisfiesAdminRoutePermission } from '@/helpers/checkPermission';
import appLogo from '@/assets/images/logo.svg';
import appLogoWhite from '@/assets/images/logo-white.svg';

const branchStore = useBranchStore();
const authStore = useAdminAuthStore();
const router = useRouter();
const route = useRoute();
const submitting = ref(false);

const { accessibleBranches, selectedBranchId } = storeToRefs(branchStore);

const currentYear = new Date().getFullYear();

const redirectPath = computed(() => {
    const redirect = route.query.redirect;
    return typeof redirect === 'string' && redirect.startsWith('/') ? redirect : '/admin/dashboard';
});

const canOpenBranchSettings = computed(() =>
    satisfiesAdminRoutePermission(getAdminRoutePermission('admin.branch-list'))
);

onMounted(async () => {
    await branchStore.getMyBranches();
    await branchStore.ensureSelectedBranchLoaded();

    if (!accessibleBranches.value.data.length && canOpenBranchSettings.value) {
        await router.replace({ name: 'admin.branch-list' });
        return;
    }

    if (accessibleBranches.value.data.length === 1 && !selectedBranchId.value) {
        branchStore.setSelectedBranch(accessibleBranches.value.data[0]);
        await continueWithSelection();
    }
});

const goToRedirect = () => {
    window.location.href = redirectPath.value;
};

const selectBranch = (branch) => {
    branchStore.setSelectedBranch(branch);
};

const continueWithSelection = async () => {
    if (!selectedBranchId.value) {
        return;
    }

    submitting.value = true;
    try {
        await authStore.refreshPermissions();
        toast(200, 'Branch selected successfully');
        goToRedirect();
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
.branch-option {
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    background: #fff;
    padding: 1rem 1.125rem;
    text-align: left;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.branch-option:hover:not(:disabled),
.branch-option.active {
    border-color: var(--bs-primary);
    box-shadow: 0 8px 24px rgba(var(--bs-primary-rgb), 0.12);
    background: rgba(var(--bs-primary-rgb), 0.03);
}

.branch-option:disabled {
    opacity: 0.7;
    cursor: wait;
}

.branch-option-top {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 10px;
}

.branch-option-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--bs-light);
    color: var(--bs-secondary);
    font-size: 20px;
    flex-shrink: 0;
    transition: background 0.2s ease, color 0.2s ease;
}

.branch-option-icon--active,
.branch-option.active .branch-option-icon {
    background: var(--bs-primary);
    color: #fff;
}

.branch-option-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--bs-heading-color);
    line-height: 1.35;
}

.branch-option-code {
    font-size: 0.8125rem;
    color: var(--bs-secondary-color);
    margin-top: 2px;
}

.branch-option-check {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--bs-primary);
    color: #fff;
    flex-shrink: 0;
}

.branch-option-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 0.8125rem;
    color: var(--bs-secondary-color);
    padding-left: 56px;
}

@media (max-width: 767.98px) {
    .branch-option-meta {
        padding-left: 0;
    }
}
</style>
