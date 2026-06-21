<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeModal"
        size="xl"
        title="Create BOM">
        <template #modal-body>
            <form @submit.prevent="handleSubmit" class="row g-3">
                <!-- Output product -->
                <div class="col-12">
                    <label class="form-label">
                        Output Product <span class="text-danger">*</span>
                    </label>
                    <div v-if="selectedOutputVariant" class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary px-3 py-2 fs-13">
                            <i class="ti ti-package me-1"></i>{{ selectedOutputVariant.name }}
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                            @click="clearOutputVariant">
                            <i class="ti ti-x me-1"></i>Change
                        </button>
                    </div>
                    <ProductVariantSearchInput
                        v-else
                        label=""
                        placeholder="Search output product..."
                        physical-only
                        @select="onOutputVariantSelected"
                    />
                    <div v-if="errors.product_variant_id" class="text-danger small mt-1">
                        {{ errors.product_variant_id }}
                    </div>
                </div>

                <!-- Name, version, output qty, flags -->
                <div class="col-md-5">
                    <VInput
                        id="bom_name"
                        v-model="form.name"
                        label="BOM Name"
                        required
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-2">
                    <VInput
                        id="bom_version"
                        v-model="form.version"
                        label="Version"
                    />
                </div>
                <div class="col-md-2">
                    <VInput
                        id="bom_output_qty"
                        v-model="form.output_qty"
                        input-type="number"
                        label="Output Qty"
                        required
                        @validate="validateField('output_qty')"
                        :error="errors.output_qty"
                    />
                </div>
                <div class="col-md-3 d-flex align-items-end gap-4 pb-2">
                    <div class="form-check">
                        <input
                            v-model="form.is_default"
                            type="checkbox"
                            class="form-check-input"
                            id="bom_is_default"
                        />
                        <label class="form-check-label" for="bom_is_default">Default BOM</label>
                    </div>
                    <div class="form-check">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="form-check-input"
                            id="bom_is_active"
                        />
                        <label class="form-check-label" for="bom_is_active">Active</label>
                    </div>
                    <div class="form-check">
                        <input
                            v-model="form.is_backflush"
                            type="checkbox"
                            class="form-check-input"
                            id="bom_is_backflush"
                        />
                        <label class="form-check-label" for="bom_is_backflush" title="Auto-consume materials at standard on completion">
                            Backflush materials
                        </label>
                    </div>
                    <div class="form-check">
                        <input
                            v-model="form.is_subcontracted"
                            type="checkbox"
                            class="form-check-input"
                            id="bom_is_subcontracted"
                        />
                        <label class="form-check-label" for="bom_is_subcontracted" title="Output is produced by an external vendor for a service charge">
                            Subcontracted
                        </label>
                    </div>
                </div>

                <!-- Subcontracting -->
                <div v-if="form.is_subcontracted" class="col-12">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <VInput
                                v-model="form.subcontract_charge"
                                input-type="number"
                                label="Subcontract charge / unit"
                            />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subcontractor</label>
                            <select v-model="form.subcontractor_party_id" class="form-select">
                                <option :value="null">Select vendor…</option>
                                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Materials -->
                <div class="col-12">
                    <label class="form-label">
                        Raw Materials / Components <span class="text-danger">*</span>
                    </label>
                    <ProductVariantSearchInput
                        label=""
                        placeholder="Search and add a material..."
                        physical-only
                        @select="onMaterialSelected"
                    />
                    <div v-if="errors.items" class="text-danger small mt-1">{{ errors.items }}</div>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Material</th>
                                    <th style="width:110px">Qty <span class="text-danger">*</span></th>
                                    <th style="width:130px">Type</th>
                                    <th style="width:120px">Rate</th>
                                    <th style="width:110px">Wastage %</th>
                                    <th style="width:44px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!form.items.length">
                                    <td colspan="7" class="text-center text-muted py-3">
                                        Search and select a material above to add lines.
                                    </td>
                                </tr>
                                <tr v-for="(item, i) in form.items" :key="i">
                                    <td>{{ i + 1 }}</td>
                                    <td>{{ item._label }}</td>
                                    <td>
                                        <input
                                            v-model="item.quantity"
                                            type="number"
                                            step="0.0001"
                                            min="0.0001"
                                            class="form-control form-control-sm"
                                        />
                                    </td>
                                    <td>
                                        <select v-model="item.item_type" class="form-select form-select-sm">
                                            <option value="material">Material</option>
                                            <option value="labour">Labour</option>
                                            <option value="overhead">Overhead</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input
                                            v-if="item.item_type !== 'material'"
                                            v-model="item.standard_rate"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="form-control form-control-sm"
                                            title="Standard cost rate per unit (absorbed into finished goods)"
                                        />
                                        <span v-else class="text-muted small">—</span>
                                    </td>
                                    <td>
                                        <input
                                            v-model="item.wastage_pct"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="form-control form-control-sm"
                                        />
                                    </td>
                                    <td class="text-center">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-link text-danger p-0"
                                            @click="form.items.splice(i, 1)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top mt-2">
                    <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                        Save BOM
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { object, string, number, array } from 'yup';
import VModal from '@/components/base/VModal.vue';
import VInput from '@/components/base/VInput.vue';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import { useBomStore } from '@/stores/admin/inventory/bom.js';
import { usePartyStore } from '@/stores/admin/party.js';
import { useYup } from '@/helpers/yup.js';
import { toast } from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';

const createModalOpened = defineModel('createModalOpened');
const emit = defineEmits(['saved']);

const bomStore = useBomStore();
const partyStore = usePartyStore();
const isSubmitting = ref(false);
const selectedOutputVariant = ref(null);
const suppliers = ref([]);

async function loadSuppliers() {
    try {
        await partyStore.getParties({ filter: { type: 'supplier', limit: 200 } });
        suppliers.value = partyStore.parties.data ?? [];
    } catch {
        suppliers.value = [];
    }
}

watch(createModalOpened, (open) => {
    if (open && !suppliers.value.length) {
        loadSuppliers();
    }
});

const initialState = () => ({
    product_variant_id: null,
    name: '',
    version: '1.0',
    output_qty: 1,
    is_active: true,
    is_default: false,
    is_backflush: false,
    is_subcontracted: false,
    subcontract_charge: 0,
    subcontractor_party_id: null,
    remarks: '',
    items: [],
});

const form = reactive(initialState());

const validations = object({
    product_variant_id: number().required('Output product is required').nullable(),
    name: string().required('BOM name is required'),
    output_qty: number().required('Output qty is required').min(0.0001, 'Must be greater than 0'),
    items: array().min(1, 'At least one material is required'),
});

const { errors, validateField, validateForm } = useYup(form, validations);

function onOutputVariantSelected(variant) {
    selectedOutputVariant.value = variant;
    form.product_variant_id = variant.id;
}

function clearOutputVariant() {
    selectedOutputVariant.value = null;
    form.product_variant_id = null;
}

function onMaterialSelected(variant) {
    form.items.push({
        product_variant_id: variant.id,
        quantity: 1,
        item_type: 'material',
        standard_rate: 0,
        wastage_pct: 0,
        _label: variant.name,
    });
}

function closeModal() {
    createModalOpened.value = false;
}

function reset() {
    Object.assign(form, initialState());
    selectedOutputVariant.value = null;
}

async function handleSubmit() {
    const valid = await validateForm();
    if (!valid) { return; }

    isSubmitting.value = true;
    try {
        const payload = {
            ...form,
            items: form.items.map(({ _label, ...item }) => item),
        };
        await bomStore.storeBom(payload);
        toast('BOM created successfully');
        reset();
        closeModal();
        emit('saved');
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
}
</script>
