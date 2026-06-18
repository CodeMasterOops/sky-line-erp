<template>
    <VModal
        :show-modal="!!orderId"
        @close-click="orderId = null"
        size="xl"
        :title="productionStore.order.data?.order_no ?? 'Production Order Detail'">
        <template #modal-body>
            <div v-if="productionStore.order.loading" class="text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>

            <template v-else-if="productionStore.order.data?.id">
                <!-- Order header -->
                <div class="row g-3 mb-4 pb-3 border-bottom">
                    <div class="col-md-3">
                        <small class="text-muted d-block mb-1">Product</small>
                        <strong>{{ productionStore.order.data.bom?.product_variant?.product?.name }}</strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block mb-1">Warehouse</small>
                        <strong>{{ productionStore.order.data.warehouse?.name }}</strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block mb-1">Qty (Done / Planned)</small>
                        <strong>
                            {{ productionStore.order.data.produced_qty ?? 0 }}
                            / {{ productionStore.order.data.planned_qty }}
                        </strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block mb-1">Status</small>
                        <span class="badge" :class="STATUS_BADGE[productionStore.order.data.status] ?? 'bg-info'">
                            {{ productionStore.order.data.status?.replace('_', ' ') }}
                        </span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block mb-1">Planned</small>
                        <span>
                            {{ productionStore.order.data.planned_start || '—' }}
                            <template v-if="productionStore.order.data.planned_end">
                                → {{ productionStore.order.data.planned_end }}
                            </template>
                        </span>
                    </div>
                    <div v-if="productionStore.order.data.remarks" class="col-12">
                        <small class="text-muted d-block mb-1">Remarks</small>
                        <span>{{ productionStore.order.data.remarks }}</span>
                    </div>
                </div>

                <!-- Operations -->
                <template v-if="sortedOperations.length">
                    <h6 class="mb-3">Operations</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50px">Seq</th>
                                    <th>Operation</th>
                                    <th style="width:140px">Work Center</th>
                                    <th style="width:100px">Status</th>
                                    <th style="width:150px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="op in sortedOperations" :key="op.id">
                                    <td>{{ op.sequence }}</td>
                                    <td>{{ op.name }}</td>
                                    <td>{{ op.work_center || '—' }}</td>
                                    <td>
                                        <span class="badge" :class="opBadge(op.status)">
                                            {{ op.status?.replace('_', ' ') }}
                                        </span>
                                    </td>
                                    <td>
                                        <div
                                            v-if="productionStore.order.data.status === 'in_progress'"
                                            class="d-flex gap-2">
                                            <button
                                                v-if="op.status === 'pending'"
                                                type="button"
                                                class="btn btn-xs btn-outline-success"
                                                :disabled="actionLoading === op.id"
                                                @click="handleStartOperation(op.id)">
                                                <span v-if="actionLoading === op.id" class="spinner-border spinner-border-sm"></span>
                                                <span v-else><i class="ti ti-player-play me-1"></i>Start</span>
                                            </button>
                                            <button
                                                v-if="op.status === 'in_progress'"
                                                type="button"
                                                class="btn btn-xs btn-outline-primary"
                                                :disabled="actionLoading === op.id"
                                                @click="handleCompleteOperation(op.id)">
                                                <span v-if="actionLoading === op.id" class="spinner-border spinner-border-sm"></span>
                                                <span v-else><i class="ti ti-check me-1"></i>Done</span>
                                            </button>
                                            <button
                                                v-if="!['completed', 'skipped'].includes(op.status)"
                                                type="button"
                                                class="btn btn-xs btn-outline-secondary"
                                                :disabled="actionLoading === op.id"
                                                @click="handleSkipOperation(op.id)">
                                                <i class="ti ti-player-skip-forward"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- Consumptions -->
                <h6 class="mb-3">Material Consumptions</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Material</th>
                                <th style="width:110px">Required Qty</th>
                                <th style="width:130px">
                                    <template v-if="isInProgress">Consumed Qty</template>
                                    <template v-else>Consumed</template>
                                </th>
                                <th v-if="isInProgress" style="width:130px">Batch</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!productionStore.order.data.consumptions?.length">
                                <td colspan="4" class="text-center text-muted py-3">No consumptions.</td>
                            </tr>
                            <tr v-for="c in completeForm.consumptions" :key="c.id">
                                <td>{{ c.product_variant?.product?.name }}</td>
                                <td>{{ c.required_qty }}</td>
                                <td>
                                    <input
                                        v-if="isInProgress"
                                        v-model="c.consumed_qty"
                                        type="number"
                                        min="0"
                                        step="0.001"
                                        class="form-control form-control-sm"
                                    />
                                    <span v-else>{{ c.consumed_qty ?? '—' }}</span>
                                </td>
                                <td v-if="isInProgress">
                                    <input
                                        v-model="c.batch_id"
                                        type="number"
                                        class="form-control form-control-sm"
                                        placeholder="Batch ID"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Complete Order -->
                <div v-if="isInProgress" class="card border-primary">
                    <div class="card-header bg-primary text-white py-2">
                        <h6 class="mb-0"><i class="ti ti-check-circle me-2"></i>Complete Production Order</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Actual Produced Qty <span class="text-danger">*</span>
                                </label>
                                <input
                                    v-model="completeForm.produced_qty"
                                    type="number"
                                    min="0.0001"
                                    step="0.001"
                                    class="form-control"
                                    placeholder="Qty produced"
                                />
                            </div>
                            <div class="col-md-8 d-flex justify-content-end">
                                <button
                                    type="button"
                                    class="btn btn-success"
                                    :disabled="completing || !completeForm.produced_qty"
                                    @click="handleCompleteOrder">
                                    <span v-if="completing" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="ti ti-circle-check me-1"></i>
                                    Mark Complete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </template>
    </VModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import VModal from '@/components/base/VModal.vue';
import { useProductionOrderStore } from '@/stores/admin/inventory/production.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { STATUS_BADGE } from './tableConfig.js';
import { toast } from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';

const orderId = defineModel('orderId');
const emit = defineEmits(['completed']);

const productionStore = useProductionOrderStore();
const { confirmAction, confirmDelete } = useConfirmAction();

const actionLoading = ref(null);
const completing = ref(false);

const completeForm = reactive({ produced_qty: null, consumptions: [] });

const isInProgress = computed(
    () => productionStore.order.data?.status === 'in_progress',
);

const sortedOperations = computed(() => {
    const ops = productionStore.order.data?.operations ?? [];
    return [...ops].sort((a, b) => a.sequence - b.sequence);
});

function opBadge(status) {
    const map = {
        pending:     'bg-secondary',
        in_progress: 'bg-warning text-dark',
        completed:   'bg-success',
        skipped:     'bg-light text-muted border',
    };
    return map[status] ?? 'bg-info';
}

watch(() => orderId.value, (id) => {
    if (!id) { return; }
    productionStore.getOrder(id).then(() => {
        syncCompleteForm();
    });
});

watch(() => productionStore.order.data?.consumptions, syncCompleteForm);

function syncCompleteForm() {
    const order = productionStore.order.data;
    if (!order?.id) { return; }
    completeForm.produced_qty = order.planned_qty ?? null;
    completeForm.consumptions = (order.consumptions ?? []).map(c => ({
        ...c,
        consumed_qty: c.consumed_qty ?? c.required_qty,
        batch_id: c.batch_id ?? null,
    }));
}

async function handleStartOperation(operationId) {
    actionLoading.value = operationId;
    try {
        await productionStore.startOperation(orderId.value, operationId);
        toast('Operation started');
    } catch (e) {
        showErrors(e);
    } finally {
        actionLoading.value = null;
    }
}

async function handleCompleteOperation(operationId) {
    actionLoading.value = operationId;
    try {
        await productionStore.completeOperation(orderId.value, operationId, {});
        toast('Operation completed');
    } catch (e) {
        showErrors(e);
    } finally {
        actionLoading.value = null;
    }
}

async function handleSkipOperation(operationId) {
    actionLoading.value = operationId;
    try {
        await productionStore.skipOperation(orderId.value, operationId);
        toast('Operation skipped');
    } catch (e) {
        showErrors(e);
    } finally {
        actionLoading.value = null;
    }
}

async function handleCompleteOrder() {
    const payload = {
        produced_qty: completeForm.produced_qty,
        consumptions: completeForm.consumptions.map(c => ({
            id: c.id,
            consumed_qty: Number(c.consumed_qty),
            batch_id: c.batch_id || null,
        })),
    };

    completing.value = true;
    try {
        await productionStore.completeOrder(orderId.value, payload);
        toast('Production order completed');
        orderId.value = null;
        emit('completed');
    } catch (e) {
        showErrors(e);
    } finally {
        completing.value = false;
    }
}
</script>
