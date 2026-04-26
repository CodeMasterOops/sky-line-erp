<template>
    <PageHeader title="Bin Locations" subtitle="Warehouse bin-level storage management" @refresh="fetchBins">
        <template #actions>
            <button v-can="'create_bin'" type="button" class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Add Bin
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2">
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" v-model="filter.warehouse_id" @change="fetchBins">
                            <option value="">All Warehouses</option>
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table :columns="columns" :data-source="bins" :loading="loading" row-key="id">
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'is_active'">
                                <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                    {{ record.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="d-flex gap-2">
                                    <a href="#" @click.prevent="editBin(record)"><i class="ti ti-edit"></i></a>
                                    <a href="#" @click.prevent="deleteBin(record.id)" class="text-danger"><i class="ti ti-trash"></i></a>
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
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editId ? 'Edit Bin' : 'Add Bin' }}</h5>
                    <button type="button" class="btn-close" @click="formModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select v-model="form.warehouse_id" class="form-select">
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Bin Name <span class="text-danger">*</span></label>
                            <input v-model="form.name" class="form-control" placeholder="e.g. Rack A-01-L1" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Code</label>
                            <input v-model="form.code" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Zone</label>
                            <input v-model="form.zone" class="form-control" placeholder="A, B, C" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rack</label>
                            <input v-model="form.rack" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Level</label>
                            <input v-model="form.level" class="form-control" placeholder="1, 2, 3" />
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input v-model="form.is_active" type="checkbox" class="form-check-input" id="binActive" />
                                <label class="form-check-label" for="binActive">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea v-model="form.description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="formModal = false">Cancel</button>
                    <button class="btn btn-primary" :disabled="saving" @click="saveBin">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save
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
const bins = ref([]);
const warehouses = ref([]);
const formModal = ref(false);
const editId = ref(null);
const filter = ref({ warehouse_id: '' });

const form = ref({ warehouse_id: '', name: '', code: '', zone: '', rack: '', level: '', is_active: true, description: '' });

const columns = [
    { title: 'Bin Name', dataIndex: 'name', key: 'name' },
    { title: 'Code', dataIndex: 'code', key: 'code' },
    { title: 'Warehouse', key: 'warehouse', dataIndex: ['warehouse', 'name'] },
    { title: 'Zone', dataIndex: 'zone', key: 'zone' },
    { title: 'Rack', dataIndex: 'rack', key: 'rack' },
    { title: 'Level', dataIndex: 'level', key: 'level' },
    { title: 'Status', key: 'is_active' },
    { title: 'Action', key: 'action' },
];

onMounted(() => { fetchBins(); fetchWarehouses(); });

async function fetchBins() {
    loading.value = true;
    try {
        const params = filter.value.warehouse_id ? `?warehouse_id=${filter.value.warehouse_id}` : '';
        const { data } = await apiAdmin(`bin${params}`);
        bins.value = data.data;
    } finally { loading.value = false; }
}

async function fetchWarehouses() {
    const { data } = await apiAdmin('warehouse');
    warehouses.value = data.data;
}

function openCreate() {
    editId.value = null;
    form.value = { warehouse_id: '', name: '', code: '', zone: '', rack: '', level: '', is_active: true, description: '' };
    formModal.value = true;
}

function editBin(bin) {
    editId.value = bin.id;
    form.value = { ...bin };
    formModal.value = true;
}

async function saveBin() {
    saving.value = true;
    try {
        if (editId.value) {
            await apiAdmin(`bin/${editId.value}`, 'put', form.value);
        } else {
            await apiAdmin('bin', 'post', form.value);
        }
        toast('Bin saved');
        formModal.value = false;
        fetchBins();
    } catch (e) { showErrors(e); }
    finally { saving.value = false; }
}

async function deleteBin(id) {
    const result = await Swal.fire({ title: 'Delete bin?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete' });
    if (!result.isConfirmed) return;
    await apiAdmin(`bin/${id}`, 'delete');
    toast('Bin deleted');
    fetchBins();
}
</script>
