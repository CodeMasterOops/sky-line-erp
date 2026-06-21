<template>
    <VModal
        :show-modal="!!bomId"
        @close-click="bomId = null"
        size="xl"
        :title="bomStore.bom.data?.name ?? 'BOM Detail'">
        <template #modal-body>
            <div v-if="bomStore.bom.loading" class="text-center py-5">
                <div class="spinner-border text-primary"></div>
            </div>

            <template v-else-if="bomStore.bom.data?.id">
                <!-- Header info -->
                <div class="row g-3 mb-4 pb-3 border-bottom">
                    <div class="col-md-4">
                        <small class="text-muted d-block mb-1">Output Product</small>
                        <strong>{{ bomStore.bom.data.product_variant?.product?.name }}</strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block mb-1">Version</small>
                        <strong>{{ bomStore.bom.data.version || '—' }}</strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block mb-1">Output Qty</small>
                        <strong>
                            {{ bomStore.bom.data.output_qty }}
                            {{ bomStore.bom.data.output_unit?.name }}
                        </strong>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block mb-1">Status</small>
                        <span :class="bomStore.bom.data.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                            {{ bomStore.bom.data.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block mb-1">Default</small>
                        <span v-if="bomStore.bom.data.is_default" class="badge bg-primary">Default</span>
                        <span v-else class="text-muted">—</span>
                    </div>
                </div>

                <!-- Materials -->
                <h6 class="mb-3">Raw Materials</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px">#</th>
                                <th>Material</th>
                                <th style="width:120px">Qty</th>
                                <th style="width:110px">Type</th>
                                <th style="width:100px">Wastage %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!bomStore.bom.data.items?.length">
                                <td colspan="5" class="text-center text-muted py-3">No materials defined.</td>
                            </tr>
                            <tr v-for="(item, i) in bomStore.bom.data.items" :key="item.id">
                                <td>{{ i + 1 }}</td>
                                <td>{{ item.product_variant?.product?.name }}</td>
                                <td>{{ item.quantity }} {{ item.unit?.name }}</td>
                                <td class="text-capitalize">{{ item.item_type }}</td>
                                <td>{{ item.wastage_pct ?? 0 }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Multi-level material requirements -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="mb-0">Material Requirements</h6>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted mb-0">For qty</label>
                        <input v-model.number="explodeQty" type="number" min="0.0001" step="1"
                            class="form-control form-control-sm" style="width:90px"
                            @change="loadExplosion" />
                    </div>
                </div>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Raw Material (leaf)</th>
                                <th style="width:120px">SKU</th>
                                <th style="width:140px" class="text-end">Total Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="explosionLoading">
                                <td colspan="3" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary"></div>
                                </td>
                            </tr>
                            <tr v-else-if="!explodedMaterials.length">
                                <td colspan="3" class="text-center text-muted py-3">No materials.</td>
                            </tr>
                            <tr v-for="m in explodedMaterials" :key="m.variant_id">
                                <td>{{ m.name }}</td>
                                <td>{{ m.sku || '—' }}</td>
                                <td class="text-end">{{ m.total_qty }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0">
                        Sub-assemblies are exploded to their leaf raw materials.
                    </p>
                </div>

                <!-- Operations header -->
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h6 class="mb-0">Operations</h6>
                    <button
                        v-can="'edit_bom'"
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        @click="openAddOperation">
                        <i class="ti ti-plus me-1"></i>Add Operation
                    </button>
                </div>

                <!-- Operations table -->
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px">Seq</th>
                                <th>Operation</th>
                                <th style="width:150px">Work Center</th>
                                <th style="width:130px">Duration (min)</th>
                                <th>Remarks</th>
                                <th style="width:80px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!sortedOperations.length">
                                <td colspan="6" class="text-center text-muted py-3">
                                    No operations defined.
                                </td>
                            </tr>
                            <tr v-for="op in sortedOperations" :key="op.id">
                                <td>{{ op.sequence }}</td>
                                <td>{{ op.name }}</td>
                                <td>{{ op.work_center || '—' }}</td>
                                <td>{{ op.duration_minutes ?? '—' }}</td>
                                <td>{{ op.remarks || '—' }}</td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a
                                            v-can="'edit_bom'"
                                            href="javascript:void(0);"
                                            class="text-primary"
                                            title="Edit"
                                            @click="openEditOperation(op)">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                        <a
                                            v-can="'edit_bom'"
                                            href="javascript:void(0);"
                                            class="text-danger"
                                            title="Delete"
                                            @click="handleDeleteOperation(op.id)">
                                            <i class="ti ti-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add / Edit operation form -->
                <div v-if="showOperationForm" class="card border bg-light">
                    <div class="card-body">
                        <h6 class="mb-3">{{ editOperationId ? 'Edit Operation' : 'Add Operation' }}</h6>
                        <div class="row g-2">
                            <div class="col-md-1">
                                <label class="form-label">Seq <span class="text-danger">*</span></label>
                                <input
                                    v-model="opForm.sequence"
                                    type="number"
                                    min="1"
                                    class="form-control form-control-sm"
                                />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input
                                    v-model="opForm.name"
                                    class="form-control form-control-sm"
                                />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Work Center</label>
                                <input
                                    v-model="opForm.work_center"
                                    class="form-control form-control-sm"
                                />
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Duration (min)</label>
                                <input
                                    v-model="opForm.duration_minutes"
                                    type="number"
                                    min="1"
                                    class="form-control form-control-sm"
                                />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Remarks</label>
                                <input
                                    v-model="opForm.remarks"
                                    class="form-control form-control-sm"
                                />
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3 justify-content-end">
                            <button type="button" class="btn btn-sm btn-secondary" @click="cancelOperation">
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-primary"
                                :disabled="savingOperation"
                                @click="saveOperation">
                                <span v-if="savingOperation" class="spinner-border spinner-border-sm me-1"></span>
                                Save
                            </button>
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
import { useBomStore } from '@/stores/admin/inventory/bom.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { toast } from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';

const bomId = defineModel('bomId');
const bomStore = useBomStore();
const { confirmDelete } = useConfirmAction();

const showOperationForm = ref(false);
const editOperationId = ref(null);
const savingOperation = ref(false);

const opInitialState = () => ({
    sequence: 1,
    name: '',
    work_center: '',
    duration_minutes: null,
    remarks: '',
});

const opForm = reactive(opInitialState());

const sortedOperations = computed(() => {
    const ops = bomStore.bom.data?.operations ?? [];
    return [...ops].sort((a, b) => a.sequence - b.sequence);
});

const explodeQty = ref(1);
const explodedMaterials = ref([]);
const explosionLoading = ref(false);

async function loadExplosion() {
    if (!bomId.value || !explodeQty.value || explodeQty.value <= 0) {
        return;
    }
    explosionLoading.value = true;
    try {
        const data = await bomStore.explodeBom(bomId.value, explodeQty.value);
        explodedMaterials.value = data?.materials ?? [];
    } catch {
        explodedMaterials.value = [];
    } finally {
        explosionLoading.value = false;
    }
}

watch(() => bomId.value, (id) => {
    if (!id) {
        cancelOperation();
        explodedMaterials.value = [];
        return;
    }
    explodeQty.value = 1;
    bomStore.getBom(id).then(() => loadExplosion());
});

function openAddOperation() {
    editOperationId.value = null;
    Object.assign(opForm, opInitialState());
    opForm.sequence = (bomStore.bom.data?.operations?.length ?? 0) + 1;
    showOperationForm.value = true;
}

function openEditOperation(op) {
    editOperationId.value = op.id;
    Object.assign(opForm, {
        sequence: op.sequence,
        name: op.name,
        work_center: op.work_center ?? '',
        duration_minutes: op.duration_minutes ?? null,
        remarks: op.remarks ?? '',
    });
    showOperationForm.value = true;
}

function cancelOperation() {
    showOperationForm.value = false;
    editOperationId.value = null;
}

async function saveOperation() {
    if (!opForm.sequence || !opForm.name.trim()) {
        return;
    }

    savingOperation.value = true;
    try {
        if (editOperationId.value) {
            await bomStore.updateOperation(bomId.value, editOperationId.value, opForm);
            toast('Operation updated');
        } else {
            await bomStore.storeOperation(bomId.value, opForm);
            toast('Operation added');
        }
        cancelOperation();
    } catch (e) {
        showErrors(e);
    } finally {
        savingOperation.value = false;
    }
}

function handleDeleteOperation(operationId) {
    confirmDelete(
        () => bomStore.deleteOperation(bomId.value, operationId),
    );
}
</script>
