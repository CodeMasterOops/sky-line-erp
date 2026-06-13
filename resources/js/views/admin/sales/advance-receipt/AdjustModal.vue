<template>
    <VModal
        :show-modal="!!advance"
        @close-click="closeModal"
        size="md"
        title="Adjust Advance to Invoice">
        <template #modal-body>
            <div v-if="advance" class="row g-3">
                <div class="col-12">
                    <div class="alert alert-info mb-0 py-2">
                        <strong>{{ advance.advance_no }}</strong> — Available Balance:
                        <strong>{{ formatMoney(advance.balance) }}</strong>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Invoice <span class="text-danger">*</span></label>
                    <VMultiselect
                        id="adj_invoice_id"
                        v-model="form.invoice_id"
                        :options="invoices.data"
                        label="Invoice"
                        :filter-results="false"
                        @search-change="debouncedInvoiceSearch"
                        :error="errors.invoice_id"
                    />
                </div>

                <div class="col-12">
                    <label class="form-label">Adjustment Amount <span class="text-danger">*</span></label>
                    <input
                        type="number"
                        class="form-control"
                        :class="{ 'is-invalid': errors.amount }"
                        v-model="form.amount"
                        min="0.01"
                        :max="advance.balance"
                        step="0.01"
                        placeholder="0.00"
                    />
                    <div v-if="errors.amount" class="invalid-feedback">{{ errors.amount }}</div>
                    <div class="form-text">Max: {{ formatMoney(advance.balance) }}</div>
                </div>
            </div>
        </template>

        <template #modal-footer>
            <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
            <button
                type="button"
                class="btn btn-primary"
                :disabled="saving"
                @click="submitAdjustment">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                Apply Adjustment
            </button>
        </template>
    </VModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import debounce from 'lodash/debounce';
import { useAdvanceReceiptStore } from '@/stores/admin/sales/advance-receipt.js';
import { useInvoiceStore } from '@/stores/admin/sales/invoice.js';
import { useFormValidation } from '@/composables/useFormValidation.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import showErrors from '@/helpers/showErrors.js';
import showSuccess from '@/helpers/showSuccess.js';

const props = defineProps({
    advance: { type: Object, default: null },
});

const emit = defineEmits(['update:advance', 'saved']);

const advanceStore = useAdvanceReceiptStore();
const invoiceStore = useInvoiceStore();
const { invoices } = storeToRefs(invoiceStore);

const saving = ref(false);

const form = ref({ invoice_id: '', amount: '' });

const validationRules = {
    invoice_id: { required: true },
    amount: { required: true, min: 0.01 },
};

const { errors, validateAll, clearErrors } = useFormValidation(form, validationRules);

const debouncedInvoiceSearch = debounce((query) => {
    invoiceStore.getInvoices({ filter: { search: query, status: 'approved', page: 1, limit: 20 } });
}, 300);

watch(
    () => props.advance,
    (val) => {
        if (val) {
            form.value = { invoice_id: '', amount: '' };
            clearErrors();
            invoiceStore.getInvoices({ filter: { party_id: val.party_id, status: 'approved', page: 1, limit: 20 } });
        }
    },
);

async function submitAdjustment() {
    if (!validateAll()) {
        return;
    }

    saving.value = true;

    try {
        await advanceStore.adjustAdvance(props.advance.id, form.value);
        showSuccess('Advance adjusted successfully.');
        emit('saved');
        closeModal();
    } catch (err) {
        showErrors(err);
    } finally {
        saving.value = false;
    }
}

function closeModal() {
    emit('update:advance', null);
}
</script>
