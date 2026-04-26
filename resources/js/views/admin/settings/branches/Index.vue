<template>
    <PageHeader title="Branch Management" subtitle="Manage company branches / offices" @refresh="fetchBranches">
        <template #actions>
            <button v-can="'create_branch'" type="button" class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Add Branch
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table :columns="columns" :data-source="branches" :loading="loading" row-key="id">
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
    </section>

    <!-- Create/Edit Modal -->
    <div v-if="formModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editId ? 'Edit Branch' : 'Add Branch' }}</h5>
                    <button type="button" class="btn-close" @click="formModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
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
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="formModal = false">Cancel</button>
                    <button class="btn btn-primary" :disabled="saving" @click="saveBranch">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save Branch
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { apiAdmin } from '@/helpers/api';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

const loading = ref(false);
const saving = ref(false);
const branches = ref([]);
const formModal = ref(false);
const editId = ref(null);

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

async function fetchBranches() {
    loading.value = true;
    try {
        const { data } = await apiAdmin('branch');
        branches.value = data.data;
    } finally { loading.value = false; }
}

function openCreate() {
    editId.value = null;
    form.value = { name: '', code: '', address: '', phone: '', email: '', pan: '', is_head_office: false, is_active: true };
    formModal.value = true;
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
            await apiAdmin(`branch/${editId.value}`, 'put', form.value);
        } else {
            await apiAdmin('branch', 'post', form.value);
        }
        toast('Branch saved successfully');
        formModal.value = false;
        fetchBranches();
    } catch (e) { showErrors(e); }
    finally { saving.value = false; }
}

async function deleteBranch(id) {
    const result = await Swal.fire({ title: 'Delete branch?', text: 'All branch data will be unlinked.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete' });
    if (!result.isConfirmed) return;
    await apiAdmin(`branch/${id}`, 'delete');
    toast('Branch deleted');
    fetchBranches();
}
</script>
