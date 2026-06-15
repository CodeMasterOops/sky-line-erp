<template>
    <PageHeader :title="`Challan: ${challan?.challan_no || ''}`" subtitle="Delivery Challan Detail">
        <template #actions>
            <DocumentPrintButton
                v-if="challan"
                target="#document-print-area"
                title="Delivery Challan"
                label="Print"
                button-class="btn-outline-primary me-2"
            />
            <router-link :to="{ name: 'admin.delivery-challan-list' }" class="btn btn-outline-secondary me-2">
                <i class="ti ti-arrow-left me-1"></i> Back
            </router-link>
            <button
                v-if="challan?.status === 'approved'"
                v-can="'create_invoice'"
                class="btn btn-primary me-2 no-print"
                @click="openInvoiceModal">
                <i class="ti ti-file-invoice me-1"></i> Create Invoice
            </button>
            <button
                v-if="challan?.status === 'draft'"
                v-can="'approve_delivery_challan'"
                class="btn btn-success no-print"
                @click="approve"
                :disabled="approving">
                <span v-if="approving" class="spinner-border spinner-border-sm me-1"></span>
                Approve & Issue Stock
            </button>
        </template>
    </PageHeader>

    <div v-if="loading" class="text-center py-5">
        <span class="spinner-border"></span>
    </div>

    <ChallanGrnPrintLayout
        v-else-if="challan"
        document-title="DELIVERY CHALLAN"
        :detail-data="printData"
        party-title="Customer"
        document-no-key="challan_no"
        document-date-key="challan_date"
        items-key="challan_items"
        :context-fields="challanContextFields"
    />

    <div v-if="challan && challan.status === 'approved' && stockMovements.length" class="card mt-3 no-print">
        <div class="card-body">
            <h6 class="mb-2">Stock Movements</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Product Variant</th>
                            <th class="text-end">Quantity</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="movement in stockMovements" :key="movement.id">
                            <td>{{ movement.product_variant_id }}</td>
                            <td class="text-end">{{ movement.quantity }}</td>
                            <td>{{ movement.type }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <CreateInvoice
        v-model:create-modal-opened="invoiceModalOpened"
        v-model:delivery-challan-id="invoiceDeliveryChallanId"
    />
</template>

<script setup>
import {computed, ref, onMounted} from 'vue';
import {useRoute} from 'vue-router';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';
import {useDeliveryChallanStore} from '@/stores/admin/inventory/delivery-challan.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import CreateInvoice from '@/views/admin/sales/invoice/Create.vue';
import ChallanGrnPrintLayout from '@/components/print/ChallanGrnPrintLayout.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const route = useRoute();
const deliveryChallanStore = useDeliveryChallanStore();
const {ensureBranding} = useCompanyBranding();

const challan = ref(null);
const loading = ref(false);
const approving = ref(false);
const invoiceModalOpened = ref(false);
const invoiceDeliveryChallanId = ref('');

const stockMovements = computed(() => challan.value?.stock_movements || []);

const printData = computed(() => {
    if (!challan.value) {
        return {};
    }

    return {
        ...challan.value,
        challan_date: formatDate(challan.value.challan_date),
        party_name: challan.value.party?.name || '',
        party_address: challan.value.delivery_address || challan.value.party?.address || '',
        party_phone: challan.value.party?.phone || '',
        party_pan: challan.value.party?.pan || '',
        reference_label: 'Sales Order',
        reference_value: challan.value.sales_order_id ? `#${challan.value.sales_order_id}` : '',
    };
});

const challanContextFields = computed(() => [
    {label: 'Receiver', value: challan.value?.receiver_name},
]);

const loadChallan = async () => {
    loading.value = true;
    try {
        await ensureBranding();
        const res = await deliveryChallanStore.getChallan(route.params.id);
        challan.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const approve = async () => {
    approving.value = true;
    try {
        const res = await deliveryChallanStore.approveChallan(challan.value.id);
        toast('success', res.data.message);
        await loadChallan();
    } catch (e) {
        showErrors(e);
    } finally {
        approving.value = false;
    }
};

const openInvoiceModal = () => {
    invoiceDeliveryChallanId.value = String(challan.value.id);
    invoiceModalOpened.value = true;
};

onMounted(() => { loadChallan(); });
</script>
