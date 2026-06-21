<template>
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Work Centre Load</h5>
            <span class="text-muted small">Pending minutes on active production orders</span>
        </div>
        <div class="card-body">
            <div v-if="loadRows.length" class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Work Centre</th>
                            <th style="width:140px" class="text-end">Operations</th>
                            <th style="width:160px" class="text-end">Load (minutes)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="w in loadRows" :key="w.work_center">
                            <td>{{ w.work_center }}</td>
                            <td class="text-end">{{ w.operation_count }}</td>
                            <td class="text-end fw-semibold">{{ w.total_minutes }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-muted small mb-0">No pending operations.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h5 class="mb-1">Material Requirements Plan</h5>
                <p class="text-muted small mb-0">
                    Items below their minimum stock level, with suggested production or purchase
                    and an exploded material shortfall for manufactured items.
                </p>
            </div>
            <button class="btn btn-primary" :disabled="loading" @click="loadPlan">
                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ti ti-refresh me-1"></i>
                Refresh
            </button>
        </div>

        <div class="card-body">
            <div v-if="loading" class="text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>

            <div v-else-if="!suggestions.length" class="text-center text-muted py-5">
                <i class="ti ti-circle-check fs-1 text-success d-block mb-2"></i>
                Nothing below minimum stock. All good.
            </div>

            <div v-else class="vstack gap-3">
                <p class="text-muted small mb-0">Generated {{ generatedAt }} · {{ suggestions.length }} item(s)</p>

                <div v-for="s in suggestions" :key="s.variant_id" class="border rounded-3 p-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <span class="fw-semibold">{{ s.name }}</span>
                            <span v-if="s.sku" class="text-muted small ms-2">{{ s.sku }}</span>
                            <span class="badge ms-2"
                                :class="s.action === 'produce' ? 'badge-soft-primary' : 'badge-soft-info'">
                                {{ s.action === 'produce' ? 'Produce' : 'Purchase' }}
                            </span>
                        </div>
                        <div class="small text-nowrap">
                            On hand <strong>{{ s.on_hand }}</strong> ·
                            Min <strong>{{ s.min_stock }}</strong> ·
                            Shortfall <strong class="text-danger">{{ s.shortfall }}</strong>
                        </div>
                    </div>

                    <div v-if="s.action === 'produce' && s.material_requirements.length" class="table-responsive mt-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Material</th>
                                    <th style="width:110px">SKU</th>
                                    <th style="width:120px" class="text-end">Required</th>
                                    <th style="width:120px" class="text-end">On hand</th>
                                    <th style="width:120px" class="text-end">Shortfall</th>
                                    <th style="width:100px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in s.material_requirements" :key="m.variant_id">
                                    <td>{{ m.name }}</td>
                                    <td>{{ m.sku || '—' }}</td>
                                    <td class="text-end">{{ m.required_qty }}</td>
                                    <td class="text-end">{{ m.on_hand }}</td>
                                    <td class="text-end" :class="m.shortfall > 0 ? 'text-danger fw-semibold' : ''">
                                        {{ m.shortfall }}
                                    </td>
                                    <td>
                                        <span class="badge"
                                            :class="m.action === 'purchase' ? 'badge-soft-warning' : 'badge-soft-success'">
                                            {{ m.action === 'purchase' ? 'Buy' : 'OK' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const loading = ref(false);
const suggestions = ref([]);
const generatedAt = ref('');
const loadRows = ref([]);

const loadPlan = async () => {
    loading.value = true;
    try {
        const [planRes, loadRes] = await Promise.all([
            apiAdmin('inventory-report/mrp-plan', 'get'),
            apiAdmin('inventory-report/work-center-load', 'get'),
        ]);
        suggestions.value = planRes.data.data?.suggestions ?? [];
        generatedAt.value = planRes.data.data?.generated_at ?? '';
        loadRows.value = loadRes.data.data?.rows ?? [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(loadPlan);
</script>
