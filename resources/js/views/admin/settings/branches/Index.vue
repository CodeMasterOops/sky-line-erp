<template>
    <div>
        <PageHeader title="Branch Management" subtitle="Manage company branches / offices" @refresh="fetch">
            <template #actions>
                <button v-can="'create_branch'" type="button" class="btn btn-primary" @click="openCreate">
                    <i class="ti ti-circle-plus me-2"></i> Add Branch
                </button>
            </template>
        </PageHeader>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <settings-sidebar></settings-sidebar>
                <div class="card flex-fill mb-0">
                    <div class="card-body">
                        <div class="table-responsive">
                            <a-table
                                :columns="columns"
                                :data-source="branches.data"
                                :loading="branches.loading"
                                :pagination="false"
                                row-key="id"
                            >
                                <template #bodyCell="{ column, record }">
                                    <template v-if="column.key === 'is_head_office'">
                                        <span v-if="record.is_head_office" class="badge bg-primary">Head Office</span>
                                    </template>
                                    <template v-if="column.key === 'is_active'">
                                        <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                            {{ record.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </template>
                                    <template v-if="column.key === 'action'">
                                        <div class="d-flex gap-2">
                                            <router-link
                                                :to="{ name: 'admin.branch-users', params: { branchId: record.id } }"
                                                class="text-primary"
                                                title="Manage user assignments"
                                            ><i class="ti ti-users"></i></router-link>
                                            <a href="#" @click.prevent="editBranch(record)"><i class="ti ti-edit"></i></a>
                                            <a href="#" @click.prevent="deleteBranch(record.id)" class="text-danger"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </template>
                                </template>
                            </a-table>
                            <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="branches.meta" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <VModal
        :show-modal="formModal"
        :title="editId ? 'Edit Branch' : 'Add Branch'"
        size="lg"
        @close-click="closeFormModal"
    >
        <template #modal-body>
            <form @submit.prevent="saveBranch" class="branch-form">
                <section class="branch-form__section">
                    <div class="branch-form__section-head">
                        <span class="branch-form__icon"><i class="ti ti-building-store"></i></span>
                        <div>
                            <h6 class="branch-form__title">Branch Details</h6>
                            <p class="branch-form__hint">Identify this branch within your organisation.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Branch Name <span class="text-danger">*</span></label>
                            <input v-model="form.name" class="form-control" placeholder="e.g. Kathmandu Branch" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Code <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input v-model="form.code" class="form-control" placeholder="KTM" />
                                <button
                                    v-if="!editId"
                                    type="button"
                                    class="btn btn-outline-primary"
                                    :disabled="codeLoading"
                                    @click="fetchNextCode">
                                    <span v-if="codeLoading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                    <span v-else>Generate</span>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">PAN</label>
                            <input v-model="form.pan" class="form-control" placeholder="Tax PAN" />
                        </div>
                    </div>
                </section>

                <section class="branch-form__section">
                    <div class="branch-form__section-head">
                        <span class="branch-form__icon"><i class="ti ti-address-book"></i></span>
                        <div>
                            <h6 class="branch-form__title">Contact</h6>
                            <p class="branch-form__hint">Where this branch can be reached.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-medium">Address</label>
                            <input v-model="form.address" class="form-control" placeholder="Street, City" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Phone</label>
                            <input v-model="form.phone" class="form-control" placeholder="+977 ..." />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Email</label>
                            <input v-model="form.email" class="form-control" type="email" placeholder="branch@company.com" />
                        </div>
                    </div>
                </section>

                <section class="branch-form__section">
                    <div class="branch-form__section-head">
                        <span class="branch-form__icon"><i class="ti ti-adjustments"></i></span>
                        <div>
                            <h6 class="branch-form__title">Settings</h6>
                            <p class="branch-form__hint">Control how this branch behaves.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="branch-form__toggle">
                                <div>
                                    <label class="form-check-label fw-medium" for="isHO">Head Office</label>
                                    <p class="branch-form__hint mb-0">Mark as the primary location.</p>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input v-model="form.is_head_office" type="checkbox" class="form-check-input" id="isHO" role="switch" />
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="branch-form__toggle">
                                <div>
                                    <label class="form-check-label fw-medium" for="brActive">Active</label>
                                    <p class="branch-form__hint mb-0">Inactive branches are hidden from use.</p>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input v-model="form.is_active" type="checkbox" class="form-check-input" id="brActive" role="switch" />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="branch-form__footer">
                    <button class="btn btn-light" type="button" @click="closeFormModal">Cancel</button>
                    <button class="btn btn-primary" type="submit" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        {{ editId ? 'Update Branch' : 'Save Branch' }}
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import { useBranchStore } from '@/stores/admin/settings/branch.js';
import { useNextCode } from '@/helpers/useNextCode.js';

const branchStore = useBranchStore();
const saving = ref(false);
const formModal = ref(false);
const editId = ref(null);
const { branches } = storeToRefs(branchStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => branchStore.getBranches({ filter }),
    defaults: { page: 1, limit: 10 },
});

const form = ref({ name: '', code: '', address: '', phone: '', email: '', pan: '', is_head_office: false, is_active: true });
const { loading: codeLoading, fetchNextCode } = useNextCode(form, 'code', 'branch/next-code');

const columns = [
    { title: 'Branch Name', dataIndex: 'name', key: 'name' },
    { title: 'Code', dataIndex: 'code', key: 'code' },
    { title: 'PAN', dataIndex: 'pan', key: 'pan' },
    { title: 'Phone', dataIndex: 'phone', key: 'phone' },
    { title: 'Address', dataIndex: 'address', key: 'address' },
    { title: 'Head Office', key: 'is_head_office' },
    { title: 'Status', key: 'is_active' },
    { title: 'Action', key: 'action' },
];

function openCreate() {
    editId.value = null;
    form.value = { name: '', code: '', address: '', phone: '', email: '', pan: '', is_head_office: false, is_active: true };
    formModal.value = true;
    fetchNextCode();
}

function closeFormModal() {
    formModal.value = false;
}

function editBranch(b) {
    editId.value = b.id;
    form.value = { ...b };
    formModal.value = true;
}

async function saveBranch() {
    saving.value = true;
    try {
        if (editId.value) {
            await branchStore.updateBranch(editId.value, form.value);
        } else {
            await branchStore.storeBranch(form.value);
        }
        toast('Branch saved successfully');
        closeFormModal();
        fetch();
    } catch (e) { showErrors(e); }
    finally { saving.value = false; }
}

async function deleteBranch(id) {
    const result = await Swal.fire({ title: 'Delete branch?', text: 'All branch data will be unlinked.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete' });
    if (!result.isConfirmed) return;
    await branchStore.deleteBranch(id);
    toast('Branch deleted');
    fetch();
}
</script>

<style scoped>
.branch-form__section {
    padding-bottom: 1.25rem;
    margin-bottom: 1.25rem;
    border-bottom: 1px solid var(--bs-border-color, #e9ecef);
}

.branch-form__section:last-of-type {
    border-bottom: 0;
    padding-bottom: 0.5rem;
    margin-bottom: 0;
}

.branch-form__section-head {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.branch-form__icon {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 0.5rem;
    background: var(--bs-primary-bg-subtle, #eef2ff);
    color: var(--bs-primary, #4361ee);
    font-size: 1.1rem;
}

.branch-form__title {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
}

.branch-form__hint {
    margin: 0.1rem 0 0;
    font-size: 0.78rem;
    color: var(--bs-secondary-color, #6c757d);
}

.branch-form__toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1rem;
    border: 1px solid var(--bs-border-color, #e9ecef);
    border-radius: 0.5rem;
    height: 100%;
}

.branch-form__footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-top: 1.25rem;
    margin-top: 0.5rem;
    border-top: 1px solid var(--bs-border-color, #e9ecef);
}
</style>
