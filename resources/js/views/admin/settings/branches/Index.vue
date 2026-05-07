<template>
    <div>
        <PageHeader title="Branch Management" subtitle="Manage company branches / offices" @refresh="fetchBranches(true)">
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
                            <a-table :columns="columns" :data-source="branches.data" :loading="branches.loading" row-key="id">
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
                                            <a href="#" @click.prevent="editBranch(record)"><i class="ti ti-edit"></i></a>
                                            <a href="#" @click.prevent="deleteBranch(record.id)" class="text-danger"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </template>
                                </template>
                            </a-table>
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
            <form @submit.prevent="saveBranch" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                    <input v-model="form.name" class="form-control" placeholder="Kathmandu Branch" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Code <span class="text-danger">*</span></label>
                    <input v-model="form.code" class="form-control" placeholder="KTM" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">PAN</label>
                    <input v-model="form.pan" class="form-control" />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input v-model="form.address" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Phone</label>
                    <input v-model="form.phone" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input v-model="form.email" class="form-control" type="email" />
                </div>
                <div class="col-md-6 d-flex gap-4 align-items-end">
                    <div class="form-check">
                        <input v-model="form.is_head_office" type="checkbox" class="form-check-input" id="isHO" />
                        <label class="form-check-label" for="isHO">Head Office</label>
                    </div>
                    <div class="form-check">
                        <input v-model="form.is_active" type="checkbox" class="form-check-input" id="brActive" />
                        <label class="form-check-label" for="brActive">Active</label>
                    </div>
                </div>
                <div class="col-12 text-end">
                    <button class="btn btn-danger me-1" type="button" @click="closeFormModal">Cancel</button>
                    <button class="btn btn-primary" type="submit" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                        Save Branch
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { useBranchStore } from '@/stores/admin/settings/branch.js';

const branchStore = useBranchStore();

const saving = ref(false);
const formModal = ref(false);
const editId = ref(null);
const { branches } = storeToRefs(branchStore);

const form = ref({ name: '', code: '', address: '', phone: '', email: '', pan: '', is_head_office: false, is_active: true });

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

onMounted(fetchBranches);

async function fetchBranches(refetch = false) {
    await branchStore.getBranches(refetch);
}

function openCreate() {
    editId.value = null;
    form.value = { name: '', code: '', address: '', phone: '', email: '', pan: '', is_head_office: false, is_active: true };
    formModal.value = true;
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
    } catch (e) { showErrors(e); }
    finally { saving.value = false; }
}

async function deleteBranch(id) {
    const result = await Swal.fire({ title: 'Delete branch?', text: 'All branch data will be unlinked.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete' });
    if (!result.isConfirmed) return;
    await branchStore.deleteBranch(id);
    toast('Branch deleted');
}
</script>
