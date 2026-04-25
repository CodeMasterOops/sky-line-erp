<template>
    <PageHeader title="Stock reconciliation" subtitle="On-hand quantity vs valued layer quantity by variant and warehouse">
        <template #actions>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="form-check mb-0">
                    <input
                        id="only-mismatch"
                        v-model="onlyMismatch"
                        class="form-check-input"
                        type="checkbox"
                        @change="fetchRows"
                    >
                    <label class="form-check-label" for="only-mismatch">Only mismatches</label>
                </div>
                <button
                    type="button"
                    class="btn btn-outline-primary d-flex align-items-center"
                    :disabled="loading"
                    @click="fetchRows">
                    <i class="ti ti-refresh me-2"></i>
                    Refresh
                </button>
            </div>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <div class="card-body">
            <div class="alert alert-info small mb-3" role="alert">
                <strong>Why a stock adjustment did not clear the gap:</strong>
                approved adjustments change <strong>on-hand</strong> and <strong>valued</strong> quantities by the same amount,
                so <em>Stock qty − Valued qty</em> stays the same.
                Use the actions below only after you know which side is correct (physical count vs costing subledger).
            </div>
            <p v-if="meta.row_count != null" class="text-muted small mb-2">
                {{ meta.row_count }} row(s)
                <span v-if="!onlyMismatch">(including rows in balance)</span>
            </p>
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="rows"
                    :pagination="false"
                    :loading="loading"
                    row-key="rowKey"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ index + 1 }}
                        </template>
                        <template v-else-if="column.key === 'difference'">
                            <span
                                class="fw-medium"
                                :class="record.difference === 0 ? 'text-success' : 'text-danger'">
                                {{ record.difference }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'actions'">
                            <div v-if="record.difference !== 0" class="d-flex flex-wrap gap-1">
                                <button
                                    v-can="'create_stock_adjustment'"
                                    type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    title="Increase or decrease layers only so valued qty matches on-hand"
                                    @click="confirmAlign(record, 'valued_to_stock')">
                                    Match valued → stock
                                </button>
                                <button
                                    v-can="'create_stock_adjustment'"
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    title="Set on-hand qty to the current valued total"
                                    @click="confirmAlign(record, 'stock_to_valued')">
                                    Match stock → valued
                                </button>
                            </div>
                            <span v-else class="text-muted small">—</span>
                        </template>
                    </template>
                </a-table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {onMounted, reactive, ref} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const loading = ref(false);
const onlyMismatch = ref(true);
const meta = reactive({
    row_count: null,
});

const columns = [
    {title: 'SN', key: 'sn', width: 56},
    {title: 'Product', dataIndex: 'product_name'},
    {title: 'SKU', dataIndex: 'sku', width: 120},
    {title: 'Warehouse', dataIndex: 'warehouse_name'},
    {title: 'Stock qty', dataIndex: 'stock_quantity', align: 'right'},
    {title: 'Valued qty', dataIndex: 'valued_quantity', align: 'right'},
    {title: 'Difference', key: 'difference', align: 'right'},
    {title: 'Actions', key: 'actions', width: 220},
];

const fetchRows = async () => {
    loading.value = true;
    try {
        const q = new URLSearchParams({
            only_mismatch: onlyMismatch.value ? '1' : '0',
        });
        const res = await apiAdmin(`inventory/stock-reconciliation?${q.toString()}`);
        const list = res.data.data || [];
        rows.value = list.map((r, i) => ({
            ...r,
            rowKey: `${r.product_variant_id}-${r.warehouse_id}-${i}`,
        }));
        meta.row_count = res.data.meta?.row_count ?? list.length;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchRows();
});

const confirmAlign = (record, strategy) => {
    const isValuedToStock = strategy === 'valued_to_stock';
    const title = isValuedToStock
        ? 'Match valued quantity to on-hand?'
        : 'Set on-hand quantity to valued total?';
    const text = isValuedToStock
        ? 'Layers will be increased or decreased only (on-hand stays the same). Optional: set unit cost for added quantity when there is no existing layer cost.'
        : 'The stock row will be updated to equal the sum of layer quantities (layers stay the same).';

    const options = {
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Align',
    };
    if (isValuedToStock) {
        options.input = 'text';
        options.inputLabel = 'Unit cost (optional, for new valued qty)';
        options.inputPlaceholder = 'Leave blank to use average of existing layers';
    }

    Swal.fire(options).then(async (result) => {
        if (!result.isConfirmed) {
            return;
        }
        let unitCost = null;
        if (isValuedToStock && result.value !== undefined && result.value !== '') {
            const n = Number(result.value);
            if (Number.isNaN(n) || n < 0) {
                Swal.fire({icon: 'error', title: 'Invalid unit cost'});
                return;
            }
            unitCost = n;
        }
        try {
            const body = {
                strategy,
                product_variant_id: record.product_variant_id,
                warehouse_id: record.warehouse_id,
            };
            if (unitCost !== null) {
                body.unit_cost = unitCost;
            }
            const res = await apiAdmin('inventory/stock-reconciliation/align', 'post', body);
            toast(res.status, res.data?.message ?? 'Aligned.');
            await fetchRows();
        } catch (e) {
            showErrors(e);
        }
    });
};
</script>
