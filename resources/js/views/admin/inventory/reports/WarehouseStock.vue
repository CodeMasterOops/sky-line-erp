<template>
    <PageHeader hide-action-buttons title="Warehouse Wise Stock Report" subtitle="Current stock quantities grouped by warehouse" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-building-warehouse fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Warehouses</p>
                            <h4 class="mb-0">{{ kpi.total_warehouses }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-package fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total SKUs (with stock)</p>
                            <h4 class="mb-0">{{ kpi.total_items }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-stack fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">On Hand / Available</p>
                            <h4 class="mb-0">{{ formatMoneyPlain(kpi.total_quantity) }} <span class="fs-14 text-muted">/ {{ formatMoneyPlain(kpi.total_available) }}</span></h4>
                            <small v-if="kpi.total_held > 0" class="text-warning">{{ formatMoneyPlain(kpi.total_held) }} held (recalled / quarantine / expired)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Warehouse</label>
                        <VMultiselect
                            id="warehouse_id"
                            v-model="filters.warehouse_id"
                            :options="warehouseStore.optionsTree"
                            :loading="warehouseStore.warehouses.loading"
                            placeholder="All Warehouses"
                        />
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success flex-grow-1" @click="loadReport" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Generate
                        </button>
                        <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length" title="Export CSV">
                            <i class="ti ti-file-export"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-for="group in rows" :key="group.warehouse" class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="ti ti-building-warehouse me-2"></i>{{ group.warehouse }}</span>
                <span class="text-muted small">
                    {{ group.item_count }} items &bull; {{ formatMoneyPlain(group.total_quantity) }} on hand
                    <template v-if="group.total_held > 0">
                        &bull; <span class="text-warning">{{ formatMoneyPlain(group.total_held) }} held</span>
                        &bull; {{ formatMoneyPlain(group.total_available) }} available
                    </template>
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Code</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th class="text-end">On Hand</th>
                                <th class="text-end">Held</th>
                                <th class="text-end">Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in group.items" :key="idx">
                                <td>{{ item.product_name }}</td>
                                <td>{{ item.product_code }}</td>
                                <td>{{ item.sku }}</td>
                                <td>{{ item.category }}</td>
                                <td class="text-end fw-semibold">{{ formatMoneyPlain(item.quantity) }}</td>
                                <td class="text-end" :class="item.held > 0 ? 'text-warning fw-semibold' : 'text-muted'">{{ formatMoneyPlain(item.held) }}</td>
                                <td class="text-end fw-semibold">{{ formatMoneyPlain(item.available) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="!rows.length && !loading" class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">Click Generate to load data.</div>
        </div>
    </section>
</template>

<script setup>
import {formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, computed, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import VMultiselect from '@/components/base/VMultiselect.vue';

const warehouseStore = useWarehouseStore();

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);

const filters = ref({warehouse_id: ''});

const kpi = computed(() => summary.value ?? {total_warehouses: 0, total_items: 0, total_quantity: 0, total_held: 0, total_available: 0});

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Warehouse', 'Product', 'Code', 'SKU', 'Category', 'On Hand', 'Held', 'Available'];
    const csvRows = rows.value.flatMap(g => g.items.map(item => [
        g.warehouse, item.product_name, item.product_code, item.sku, item.category, item.quantity, item.held, item.available,
    ].map(v => `"${v ?? ''}"`).join(',')));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'warehouse-stock.csv';
    a.click();
};

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (params[k] === '' || params[k] === null) { delete params[k]; } });
        const res = await apiAdmin('inventory-report/warehouse-stock', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    warehouseStore.getWarehouses();
    await loadReport();
});
</script>
