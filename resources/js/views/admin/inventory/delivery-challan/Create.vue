<template>
    <VModal
        :show-modal="showModal"
        @close-click="closeCreateModal"
        size="xl"
        modal-class="add-centered"
        title="Create Delivery Challan"
    >
        <template #modal-body>
            <div class="card border-0 shadow-none mb-0">
                <div class="card-body p-0">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Customer</label>
                            <vue-select
                                v-model="form.party_id"
                                :options="customerOptions"
                                :reduce="o => o.value"
                                placeholder="Select Customer"
                            />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Warehouse *</label>
                            <vue-select
                                v-model="form.warehouse_id"
                                :options="warehouseOptions"
                                :reduce="o => o.value"
                                placeholder="Select Warehouse"
                            />
                        </div>
                        <div class="col-md-4">
                            <VDatepicker
                                id="challan_date"
                                v-model="form.challan_date"
                                label="Challan Date"
                            />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Receiver Name</label>
                            <input
                                type="text"
                                class="form-control"
                                v-model="form.receiver_name"
                                placeholder="Person receiving goods"
                            />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Delivery Address</label>
                            <input type="text" class="form-control" v-model="form.delivery_address" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Remarks</label>
                            <input type="text" class="form-control" v-model="form.remarks" />
                        </div>
                    </div>

                    <h6 class="mb-3">Items</h6>
                    <div class="mb-3">
                        <ProductVariantSearchInput
                            label="Product"
                            required
                            @select="onVariantSelected"
                        />
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40%">Product</th>
                                    <th class="text-end">Quantity *</th>
                                    <th class="text-end">Rate</th>
                                    <th>Remarks</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!form.items.length">
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Search and select a product to add lines.
                                    </td>
                                </tr>
                                <tr v-for="(item, idx) in form.items" :key="idx">
                                    <td>
                                        <span class="fw-medium">{{ item.product_label || '-' }}</span>
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            class="form-control form-control-sm text-end"
                                            v-model="item.quantity"
                                            min="0.001"
                                            step="0.001"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            class="form-control form-control-sm text-end"
                                            v-model="item.rate"
                                            min="0"
                                            step="0.01"
                                        />
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" v-model="item.remarks" />
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger" type="button" @click="removeItem(idx)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end">
                        <button class="btn btn-secondary me-2" type="button" @click="closeCreateModal">Cancel</button>
                        <button class="btn btn-primary" type="button" @click="save" :disabled="saving">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            Save Challan
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, onMounted, ref, watch} from 'vue';
import {useRoute, useRouter} from 'vue-router';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import {useDateHelper} from '@/composables/dateHelper.js';

const emit = defineEmits(['saved']);
const route = useRoute();
const router = useRouter();
const createModalOpened = defineModel('createModalOpened');

const saving = ref(false);
const {currentAdDate} = useDateHelper();
const customerOptions = ref([]);
const warehouseOptions = ref([]);

const openedFromRoute = computed(() => route.name === 'admin.delivery-challan-create');
const showModal = computed(() => openedFromRoute.value || !!createModalOpened.value);

const initialState = () => ({
    party_id: null,
    warehouse_id: null,
    challan_date: currentAdDate,
    receiver_name: '',
    delivery_address: '',
    remarks: '',
    items: [],
});

const form = ref(initialState());

const resetForm = () => {
    form.value = initialState();
};

const closeCreateModal = async () => {
    if (openedFromRoute.value) {
        await router.push({ name: 'admin.delivery-challan-list' });
        return;
    }

    createModalOpened.value = false;
};

const removeItem = (idx) => form.value.items.splice(idx, 1);

const onVariantSelected = (variant) => {
    const existing = form.value.items.findIndex((item) => String(item.product_variant_id) === String(variant.id));
    if (existing !== -1) {
        form.value.items[existing].quantity = Number(form.value.items[existing].quantity || 0) + 1;
        return;
    }

    form.value.items.push({
        product_variant_id: variant.id,
        product_label: variantLabel(variant),
        quantity: 1,
        rate: Number(variant.sales_price ?? 0),
        remarks: '',
    });
};

const variantLabel = (variant) => {
    let label = variant.name || variant.product?.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }
    return label;
};

const save = async () => {
    saving.value = true;
    try {
        const res = await apiAdmin('delivery-challan', 'post', form.value);
        toast(201, res.data.message);
        emit('saved');
        resetForm();

        if (openedFromRoute.value) {
            await router.push({ name: 'admin.delivery-challan-list' });
        } else {
            createModalOpened.value = false;
        }
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const loadOptions = async () => {
    const [partiesRes, warehousesRes] = await Promise.all([
        apiAdmin('party', 'get', { per_page: 500, type: 'customer' }),
        apiAdmin('warehouse', 'get', { per_page: 100 }),
    ]);
    customerOptions.value = (partiesRes.data.data || []).map((p) => ({ label: p.name, value: p.id }));
    warehouseOptions.value = (warehousesRes.data.data || []).map((w) => ({ label: w.name, value: w.id }));
};

watch(
    () => showModal.value,
    (opened) => {
        if (opened) {
            resetForm();
        }
    }
);

onMounted(() => {
    loadOptions();
});
</script>
