<template>
    <PageHeader title="Fixed Assets" subtitle="Track company assets and depreciation">
        <template #actions>
            <button class="btn btn-outline-secondary me-2" @click="toggleSchedule">
                <i class="ti ti-report-analytics me-1"></i>
                {{ showSchedule ? 'Hide' : 'View' }} Schedule
            </button>
            <button class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Add Asset
            </button>
        </template>
    </PageHeader>

    <!-- Depreciation schedule panel -->
    <div v-if="showSchedule" class="card border-0 mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Fixed Asset Schedule</h6>
            <button class="btn btn-sm btn-outline-secondary" @click="loadSchedule" :disabled="scheduleLoading">
                <span v-if="scheduleLoading" class="spinner-border spinner-border-sm me-1"></span>
                Refresh
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Purchase Date</th>
                            <th class="text-end">Purchase Cost</th>
                            <th class="text-end">Accum. Depreciation</th>
                            <th class="text-end">Net Book Value</th>
                            <th class="text-end">Annual Depreciation</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="scheduleLoading">
                            <td colspan="9" class="text-center py-4">
                                <span class="spinner-border spinner-border-sm"></span>
                            </td>
                        </tr>
                        <tr v-else-if="!schedule.length">
                            <td colspan="9" class="text-center text-muted py-3">No active assets.</td>
                        </tr>
                        <tr v-for="row in schedule" :key="row.id">
                            <td class="fw-semibold small">{{ row.asset_code }}</td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.category || '—' }}</td>
                            <td>{{ formatDate(row.purchase_date) }}</td>
                            <td class="text-end">{{ formatMoney(row.purchase_cost) }}</td>
                            <td class="text-end text-danger">{{ formatMoney(row.accumulated_depreciation) }}</td>
                            <td class="text-end fw-semibold">{{ formatMoney(row.net_book_value) }}</td>
                            <td class="text-end">{{ formatMoney(row.annual_depreciation) }}</td>
                            <td>{{ row.depreciation_method }}</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="scheduleTotal.total_cost" class="table-secondary fw-bold">
                        <tr>
                            <td colspan="4">Total</td>
                            <td class="text-end">{{ formatMoney(scheduleTotal.total_cost) }}</td>
                            <td class="text-end text-danger">{{ formatMoney(scheduleTotal.total_accumulated_depreciation) }}</td>
                            <td class="text-end">{{ formatMoney(scheduleTotal.total_net_book_value) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Assets list -->
    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search asset name or code"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters"
        >
            <template #filters>
                <div class="dropdown">
                    <a
                        href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown"
                    >
                        {{ filter.status ? (filter.status === 'active' ? 'Active' : 'Disposed') : 'All Statuses' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="filter.status = ''">All Statuses</a></li>
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="filter.status = 'active'">Active</a></li>
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="filter.status = 'disposed'">Disposed</a></li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>
        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="assets"
                    :pagination="false"
                    :loading="loading"
                    @change="handleTableChange"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'purchase_date'">
                            {{ formatDate(record.purchase_date) }}
                        </template>
                        <template v-else-if="column.key === 'purchase_cost'">
                            {{ formatMoney(record.purchase_cost) }}
                        </template>
                        <template v-else-if="column.key === 'net_book_value'">
                            {{ formatMoney(record.purchase_cost - record.accumulated_depreciation) }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="record.status === 'active' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a
                                        href="javascript:void(0);"
                                        class="me-2 edit-icon p-2"
                                        @click="editAsset(record)"
                                    >
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        href="javascript:void(0);"
                                        class="p-2"
                                        @click="deleteAsset(record.id)"
                                    >
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="meta" />
            </div>
        </div>
    </div>

    <!-- Create / Edit Modal -->
    <VModal
        :show-modal="showFormModal"
        size="lg"
        :title="editingAsset ? 'Edit Fixed Asset' : 'Add Fixed Asset'"
        @close-click="showFormModal = false">
        <template #modal-body>
            <form @submit.prevent="saveAsset" class="row g-3">
                <div class="col-md-6">
                    <VInput id="asset_name" v-model="form.name" label="Asset Name" placeholder="e.g. Office Laptop" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1">
                            <VMultiselect
                                v-model="form.fixed_asset_category_id"
                                :options="categoryOptions"
                                value-prop="value"
                                name-prop="label"
                                placeholder="Category"
                                @on-input="onCategorySelect"
                            />
                        </div>
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            title="Add new category"
                            @click="showCategoryModal = true"
                        >
                            <i class="ti ti-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <VDatepicker id="purchase_date" label="Purchase Date" v-model="form.purchase_date" required />
                </div>
                <div class="col-md-6">
                    <VInput id="purchase_cost" v-model="form.purchase_cost" label="Purchase Cost (NPR)" input-type="number" required />
                </div>
                <div class="col-md-6">
                    <VInput id="salvage_value" v-model="form.salvage_value" label="Salvage Value (NPR)" input-type="number" />
                </div>
                <div class="col-md-6">
                    <VInput id="useful_life_years" v-model="form.useful_life_years" label="Useful Life (years)" input-type="number" :min-value="0.5" required />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="depreciation_method"
                        v-model="form.depreciation_method"
                        label="Depreciation Method"
                        :options="depreciationMethods"
                        value-prop="value"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <VInput id="asset_notes" v-model="form.notes" label="Notes" placeholder="Optional notes" />
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-cancel" @click="showFormModal = false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save Asset
                    </button>
                </div>
            </form>
        </template>
    </VModal>

    <!-- Add Category Modal -->
    <VModal
        :show-modal="showCategoryModal"
        size="md"
        title="Add Asset Category"
        @close-click="showCategoryModal = false">
        <template #modal-body>
            <form @submit.prevent="saveCategory" class="row g-3">
                <div class="col-12">
                    <VInput id="category_name" v-model="categoryForm.name" label="Category Name" required />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="category_method"
                        v-model="categoryForm.depreciation_method"
                        label="Default Depreciation Method"
                        :options="depreciationMethods"
                        value-prop="value"
                    />
                </div>
                <div class="col-md-6">
                    <VInput id="category_useful_life" v-model="categoryForm.useful_life_years" label="Default Useful Life (years)" input-type="number" :min-value="0.5" />
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-cancel" @click="showCategoryModal = false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="savingCategory">
                        <span v-if="savingCategory" class="spinner-border spinner-border-sm me-1"></span>
                        Save Category
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { ref, computed } from 'vue';
import { formatDate } from '@/helpers/helper.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import Swal from 'sweetalert2';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VPagination from '@/components/base/VPagination.vue';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import VDatepicker from '@/components/base/VDatepicker.vue';

const assets = ref([]);
const meta = ref({ total: 0, current_page: 1, per_page: 15, from: null, to: null, last_page: 1 });
const loading = ref(false);
const showFormModal = ref(false);
const showCategoryModal = ref(false);
const showSchedule = ref(false);
const saving = ref(false);
const savingCategory = ref(false);
const scheduleLoading = ref(false);
const editingAsset = ref(null);
const categoryOptions = ref([]);
const schedule = ref([]);
const scheduleTotal = ref({});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Code', dataIndex: 'asset_code', key: 'asset_code' },
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Category', customRender: ({ record }) => record.category?.name || '—' },
    { title: 'Purchase Date', key: 'purchase_date' },
    { title: 'Cost (NPR)', key: 'purchase_cost' },
    { title: 'Net Book Value', key: 'net_book_value' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action' },
];

const defaultForm = () => ({
    name: '',
    fixed_asset_category_id: null,
    purchase_date: new Date().toISOString().split('T')[0],
    purchase_cost: 0,
    salvage_value: 0,
    useful_life_years: 5,
    depreciation_method: 'slm',
    notes: '',
});

const form = ref(defaultForm());
const categoryForm = ref({ name: '', depreciation_method: 'slm', useful_life_years: 5 });

const depreciationMethods = [
    { value: 'slm', name: 'Straight Line Method (SLM)' },
    { value: 'wdv', name: 'Written Down Value (WDV)' },
];

const fetchAssets = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('fixed-asset', 'get', {
            page: filter.page,
            per_page: filter.limit,
            search: filter.search || undefined,
            status: filter.status || undefined,
        });
        assets.value = res.data.data || [];
        const m = res.data.meta || res.data;
        meta.value = {
            total: m.total ?? 0,
            current_page: m.current_page ?? filter.page,
            per_page: m.per_page ?? filter.limit,
            from: m.from ?? null,
            to: m.to ?? null,
            last_page: m.last_page ?? 1,
        };
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 15 },
    onFilter: fetchAssets,
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => meta.value),
    filter,
});

const loadCategories = async () => {
    try {
        const res = await apiAdmin('fixed-asset/categories', 'get');
        categoryOptions.value = (res.data.data || []).map((c) => ({
            label: c.name,
            value: c.id,
            item: c,
        }));
    } catch { /* ignore */ }
};

const toggleSchedule = () => {
    showSchedule.value = !showSchedule.value;
    if (showSchedule.value && !schedule.value.length) {
        loadSchedule();
    }
};

const loadSchedule = async () => {
    scheduleLoading.value = true;
    try {
        const res = await apiAdmin('fixed-asset/schedule', 'get');
        schedule.value = res.data.data || [];
        scheduleTotal.value = res.data.summary || {};
    } catch (e) {
        showErrors(e);
    } finally {
        scheduleLoading.value = false;
    }
};

const onCategorySelect = (value) => {
    const cat = categoryOptions.value.find((o) => o.value === value)?.item;
    if (cat) {
        form.value.depreciation_method = cat.depreciation_method ?? form.value.depreciation_method;
        form.value.useful_life_years = cat.useful_life_years ?? form.value.useful_life_years;
    }
};

const openCreate = () => {
    editingAsset.value = null;
    form.value = defaultForm();
    showFormModal.value = true;
};

const editAsset = (asset) => {
    editingAsset.value = asset;
    form.value = {
        name: asset.name,
        fixed_asset_category_id: asset.fixed_asset_category_id,
        purchase_date: asset.purchase_date,
        purchase_cost: asset.purchase_cost,
        salvage_value: asset.salvage_value ?? 0,
        useful_life_years: asset.useful_life_years,
        depreciation_method: asset.depreciation_method,
        notes: asset.notes ?? '',
    };
    showFormModal.value = true;
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
        showFormModal.value = false;
        await fetchAssets();
        if (showSchedule.value) {
            await loadSchedule();
        }
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const saveCategory = async () => {
    savingCategory.value = true;
    try {
        const res = await apiAdmin('fixed-asset/categories', 'post', categoryForm.value);
        toast('success', 'Category created.');
        showCategoryModal.value = false;
        categoryForm.value = { name: '', depreciation_method: 'slm', useful_life_years: 5 };
        await loadCategories();
        if (res.data.data?.id) {
            form.value.fixed_asset_category_id = res.data.data.id;
        }
    } catch (e) {
        showErrors(e);
    } finally {
        savingCategory.value = false;
    }
};

const deleteAsset = async (id) => {
    const result = await Swal.fire({
        title: 'Delete asset?',
        text: 'This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
    });
    if (!result.value) return;
    try {
        await apiAdmin(`fixed-asset/${id}`, 'delete');
        toast('success', 'Asset deleted.');
        await fetchAssets();
    } catch (e) {
        showErrors(e);
    }
};

loadCategories();
</script>
