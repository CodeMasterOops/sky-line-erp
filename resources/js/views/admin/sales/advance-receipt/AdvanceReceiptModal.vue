<template>
    <VModal
        :show-modal="!!open"
        @close-click="closeModal"
        size="lg"
        :title="editId ? 'Edit Advance Receipt' : 'New Advance Receipt'">
        <template #modal-body>
            <VLoader v-if="loading" loader-type="progress" />
            <div v-else class="card border-0 shadow-none">
                <div class="card-body border-0 p-0">
                    <form @submit.prevent="saveAdvance('draft')" class="row g-3">
                        <div class="col-lg-6">
                            <VDatepicker
                                id="advance_date"
                                v-model="form.advance_date"
                                input-type="date"
                                label="Advance Date"
                                required
                                @validate="validateField('advance_date')"
                                :error="errors.advance_date"
                            />
                        </div>

                        <div class="col-12">
                            <VMultiselect
                                id="adv_party_id"
                                v-model="form.party_id"
                                :options="parties.data"
                                label="Customer"
                                required
                                :filter-results="false"
                                @validate="validateField('party_id')"
                                @search-change="debouncedPartySearch"
                                :error="errors.party_id"
                            />
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select
                                id="adv_payment_method"
                                class="form-select"
                                v-model="form.payment_method"
                                @change="validateField('payment_method')">
                                <option v-for="m in paymentMethodOptions" :key="m.value" :value="m.value">
                                    {{ m.label }}
                                </option>
                            </select>
                            <div v-if="errors.payment_method" class="invalid-feedback d-block">{{ errors.payment_method }}</div>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Deposit Account <span class="text-danger">*</span></label>
                            <select
                                id="adv_account_id"
                                class="form-select"
                                v-model="form.account_id"
                                @change="validateField('account_id')">
                                <option value="">Select account</option>
                                <option v-for="acc in accounts.data" :key="acc.id" :value="acc.id">
                                    {{ acc.name }}
                                </option>
                            </select>
                            <div v-if="errors.account_id" class="invalid-feedback d-block">{{ errors.account_id }}</div>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                class="form-control"
                                :class="{ 'is-invalid': errors.amount }"
                                v-model="form.amount"
                                min="0.01"
                                step="0.01"
                                placeholder="0.00"
                                @blur="validateField('amount')"
                            />
                            <div v-if="errors.amount" class="invalid-feedback">{{ errors.amount }}</div>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Reference No</label>
                            <input
                                type="text"
                                class="form-control"
                                v-model="form.reference_no"
                                placeholder="Cheque/ref number"
                            />
                        </div>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea
                                class="form-control"
                                v-model="form.remarks"
                                rows="2"
                                placeholder="Optional notes"
                            ></textarea>
                        </div>
                    </form>
                </div>
            </div>
        </template>

        <template #modal-footer>
            <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
            <button
                type="button"
                class="btn btn-outline-primary me-2"
                :disabled="saving"
                @click="saveAdvance('draft')">
                Save as Draft
            </button>
            <button
                type="button"
                class="btn btn-primary"
                :disabled="saving"
                @click="saveAdvance('approved')">
                <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                Save & Approve
            </button>
        </template>
    </VModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import debounce from 'lodash/debounce';
import { useAdvanceReceiptStore } from '@/stores/admin/sales/advance-receipt.js';
import { usePartyStore } from '@/stores/admin/party.js';
import { useAccountStore } from '@/stores/admin/accounting/account.js';
import { useFormValidation } from '@/composables/useFormValidation.js';
import showErrors from '@/helpers/showErrors.js';
import showSuccess from '@/helpers/showSuccess.js';

const props = defineProps({
    open: { type: [Boolean, Number, String], default: false },
    editId: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:open', 'saved']);

const advanceStore = useAdvanceReceiptStore();
const partyStore = usePartyStore();
const accountStore = useAccountStore();

const { parties } = storeToRefs(partyStore);
const { accounts } = storeToRefs(accountStore);

const loading = ref(false);
const saving = ref(false);

const paymentMethodOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'card', label: 'Card' },
    { value: 'online', label: 'Online' },
    { value: 'other', label: 'Other' },
];

const getInitialForm = () => ({
    advance_date: new Date().toISOString().slice(0, 10),
    party_id: '',
    payment_method: 'cash',
    account_id: '',
    amount: '',
    reference_no: '',
    remarks: '',
});

const form = ref(getInitialForm());

const validationRules = {
    advance_date: { required: true },
    party_id: { required: true },
    payment_method: { required: true },
    account_id: { required: true },
    amount: { required: true, min: 0.01 },
};

const { errors, validateField, validateAll, clearErrors } = useFormValidation(form, validationRules);

const debouncedPartySearch = debounce((query) => {
    partyStore.getParties({ filter: { search: query, type: 'customer', page: 1, limit: 20 } });
}, 300);

watch(
    () => props.open,
    async (val) => {
        if (!val) {
            return;
        }

        clearErrors();

        if (props.editId) {
            loading.value = true;
            await advanceStore.getAdvance(props.editId);
            const data = advanceStore.advance.data;
            form.value = {
                advance_date: data.advance_date || '',
                party_id: data.party_id || '',
                payment_method: data.payment_method || 'cash',
                account_id: data.account_id || '',
                amount: data.amount || '',
                reference_no: data.reference_no || '',
                remarks: data.remarks || '',
            };
            loading.value = false;
        } else {
            form.value = getInitialForm();
        }

        partyStore.getParties({ filter: { type: 'customer', page: 1, limit: 20 } });
        accountStore.getAccounts({ filter: { page: 1, limit: 100 } });
    },
);

async function saveAdvance(status) {
    if (!validateAll()) {
        return;
    }

    saving.value = true;

    const payload = { ...form.value, status };

    try {
        if (props.editId) {
            await advanceStore.updateAdvance(props.editId, payload);
        } else {
            await advanceStore.storeAdvance(payload);
        }

        if (status === 'approved') {
            const id = props.editId || advanceStore.advances.data[0]?.id;
            if (id) {
                await advanceStore.approveAdvance(id);
            }
        }

        showSuccess(props.editId ? 'Advance updated.' : 'Advance created.');
        emit('saved');
        closeModal();
    } catch (err) {
        showErrors(err);
    } finally {
        saving.value = false;
    }
}

function closeModal() {
    emit('update:open', false);
}
</script>
