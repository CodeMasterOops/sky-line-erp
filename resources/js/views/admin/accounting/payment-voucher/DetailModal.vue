<template>
    <VModal
        :show-modal="!!detailVoucherId"
        @close-click="closeModal"
        modal-class="sales-details-modal"
        size="xl"
        title="Payment voucher detail">
        <template #modal-body>
            <VLoader v-if="voucher.loading" loader-type="progress"/>
            <VoucherPrintLayout
                v-else-if="detailData.id"
                document-title="Payment Voucher"
                :detail-data="detailData"
                account-label="Paid From"
                :account-name="detailData.paid_from_account"
                counterparty-label="Paid To"
                :counterparty-name="paidToAccount"
            />

            <div v-if="detailData.id && !voucher.loading" class="d-flex gap-2 mt-3 no-print">
                <DocumentPrintButton target="#document-print-area" title="Payment Voucher" label="Print" button-class="btn-sm" />
            </div>
        </template>
    </VModal>
</template>

<script setup>
import {computed, watch} from 'vue';
import {storeToRefs} from 'pinia';
import {usePaymentVoucherStore} from '@/stores/admin/accounting/payment-voucher.js';
import {useCompanyBranding} from '@/composables/useCompanyBranding.js';
import VoucherPrintLayout from '@/components/print/VoucherPrintLayout.vue';
import DocumentPrintButton from '@/components/print/DocumentPrintButton.vue';

const voucherStore = usePaymentVoucherStore();
const {voucher} = storeToRefs(voucherStore);
const {ensureBranding} = useCompanyBranding();

const detailVoucherId = defineModel('detailVoucherId', {type: String, default: ''});
const detailData = computed(() => voucher.value.data || {});

const paidToAccount = computed(() =>
    (detailData.value.items || []).map((item) => item.account).filter(Boolean).join(', ') || '—',
);

watch(() => detailVoucherId.value, async (id) => {
    if (id) {
        await ensureBranding();
        voucherStore.getVoucher(id);
    }
});

const closeModal = () => { detailVoucherId.value = ''; };
</script>
