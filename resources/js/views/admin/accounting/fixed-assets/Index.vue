<template>
    <PageHeader title="Fixed Assets Register" subtitle="Track company assets and depreciation">
        <template #actions>
            <button class="btn btn-outline-secondary me-2" @click="showSchedule = !showSchedule">
                <i class="ti ti-report-analytics me-1"></i> Schedule
            </button>
            <button class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Add Asset
            </button>
        </template>
    </PageHeader>

    <div v-if="showSchedule" class="card border-0 mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Fixed Asset Schedule</h6>
            <button class="btn btn-sm btn-outline-secondary" @click="loadSchedule">Refresh</button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Asset Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Purchase Date</th>
                            <th class="text-end">Purchase Cost</th>
                            <th class="text-end">Acc. Depreciation</th>
                            <th class="text-end">Net Book Value</th>
                            <th class="text-end">Annual Depreciation</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in schedule" :key="row.id">
                            <td>{{ row.asset_code }}</td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.category }}</td>
                            <td>{{ formatDate(row.purchase_date) }}</td>
                            <td class="text-end">{{ fmt(row.purchase_cost) }}</td>
                            <td class="text-end text-danger">{{ fmt(row.accumulated_depreciation) }}</td>
                            <td class="text-end fw-semibold">{{ fmt(row.net_book_value) }}</td>
                            <td class="text-end">{{ fmt(row.annual_depreciation) }}</td>
                            <td>{{ row.depreciation_method }}</td>
                        </tr>
                        <tr v-if="!schedule.length">
                            <td colspan="9" class="text-center text-muted py-3">No active assets.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="scheduleTotal.total_cost" class="table-secondary fw-bold">
                        <tr>
                            <td colspan="4">Total</td>
                            <td class="text-end">{{ fmt(scheduleTotal.total_cost) }}</td>
                            <td class="text-end text-danger">{{ fmt(scheduleTotal.total_accumulated_depreciation) }}</td>
                            <td class="text-end">{{ fmt(scheduleTotal.total_net_book_value) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Purchase Date</th>
                            <th class="text-end">Cost</th>
                            <th class="text-end">Net Book Value</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="8" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>
                        <tr v-else-if="!assets.length"><td colspan="8" class="text-center text-muted py-4">No fixed assets found.</td></tr>
                        <tr v-for="asset in assets" :key="asset.id">
                            <td>{{ asset.asset_code }}</td>
                            <td>{{ asset.name }}</td>
                            <td>{{ asset.category?.name || '-' }}</td>
                            <td>{{ formatDate(asset.purchase_date) }}</td>
                            <td class="text-end">NPR {{ fmt(asset.purchase_cost) }}</td>
                            <td class="text-end">NPR {{ fmt(asset.purchase_cost - asset.accumulated_depreciation) }}</td>
                            <td><span class="badge" :class="asset.status === 'active' ? 'bg-success' : 'bg-secondary'">{{ asset.status }}</span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary" @click="editAsset(asset)"><i class="ti ti-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" @click="deleteAsset(asset.id)"><i class="ti ti-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showForm" class="modal d-block" style="background: rgba(0,0,0,0.5)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editingAsset ? 'Edit' : 'Add' }} Fixed Asset</h5>
                    <button class="btn-close" @click="showForm = false"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Asset Name *</label>
                            <input type="text" class="form-control" v-model="form.name" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <vue-select v-model="form.fixed_asset_category_id" :options="categoryOptions" :reduce="o => o.value" placeholder="Select category" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Purchase Date *</label>
                            <input type="date" class="form-control" v-model="form.purchase_date" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Purchase Cost *</label>
                            <input type="number" class="form-control" v-model="form.purchase_cost" min="0" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Salvage Value</label>
                            <input type="number" class="form-control" v-model="form.salvage_value" min="0" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Useful Life (years) *</label>
                            <input type="number" class="form-control" v-model="form.useful_life_years" min="0.5" step="0.5" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Depreciation Method *</label>
                            <select class="form-select" v-model="form.depreciation_method">
                                <option value="slm">Straight Line Method (SLM)</option>
                                <option value="wdv">Written Down Value (WDV)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showForm = false">Cancel</button>
                    <button class="btn btn-primary" @click="saveAsset" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';
import Swal from 'sweetalert2';

const assets = ref([]);
const loading = ref(false);
const showForm = ref(false);
const showSchedule = ref(false);
const saving = ref(false);
const editingAsset = ref(null);
const categoryOptions = ref([]);
const schedule = ref([]);
const scheduleTotal = ref({});

const defaultForm = () => ({
    name: '', fixed_asset_category_id: null, purchase_date: new Date().toISOString().split('T')[0],
    purchase_cost: 0, salvage_value: 0, useful_life_years: 5, depreciation_method: 'slm',
});

const form = ref(defaultForm());

const loadAssets = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('fixed-asset', 'get');
        assets.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const loadSchedule = async () => {
    try {
        const res = await apiAdmin('fixed-asset/schedule', 'get');
        schedule.value = res.data.data || [];
        scheduleTotal.value = res.data.summary || {};
    } catch (e) { showErrors(e); }
};

const loadCategories = async () => {
    try {
        const res = await apiAdmin('fixed-asset/categories', 'get');
        categoryOptions.value = (res.data.data || []).map(c => ({ label: c.name, value: c.id }));
    } catch (e) { /* ignore */ }
};

const openCreate = () => {
    editingAsset.value = null;
    form.value = defaultForm();
    showForm.value = true;
};

const editAsset = (asset) => {
    editingAsset.value = asset;
    form.value = { ...asset, fixed_asset_category_id: asset.fixed_asset_category_id };
    showForm.value = true;
};

const saveAsset = async () => {
    saving.value = true;
    try {
        if (editingAsset.value) {
            await apiAdmin(`fixed-asset/${editingAsset.value.id}`, 'put', form.value);
            toast('success', 'Asset updated.');
        } else {
            await apiAdmin('fixed-asset', 'post', form.value);
            toast('success', 'Asset created.');
        }
        showForm.value = false;
        await loadAssets();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const deleteAsset = async (id) => {
    const result = await Swal.fire({ title: 'Delete asset?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' });
    if (!result.value) return;
    try {
        await apiAdmin(`fixed-asset/${id}`, 'delete');
        toast('success', 'Deleted.');
        await loadAssets();
    } catch (e) { showErrors(e); }
};

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

onMounted(() => {
    loadAssets();
    loadCategories();
});
</script>
