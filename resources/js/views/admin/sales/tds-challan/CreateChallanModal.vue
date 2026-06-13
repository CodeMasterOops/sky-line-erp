<template>
    <VModal
        :show-modal="!!open"
        @close-click="closeModal"
        size="lg"
        title="Create TDS Challan">
        <template #modal-body>
            <form @submit.prevent="submitForm" class="row g-3">
                <div class="col-md-6">
                    <VMultiselect
                        id="party_id"
                        v-model="form.party_id"
                        :options="parties.data"
                        label="Party (Customer)"
                        required
                        :filter-results="false"
                        @search-change="debouncedPartySearch"
                        @update:model-value="onPartyChange"
                        :error="errors.party_id"
                    />
                </div>
                <div class="col-md-6">
                    <div class="mb-0">
                        <label class="form-label">Period Month <span class="text-danger">*</span></label>
                        <select class="form-select" v-model="form.period_month" @change="onMonthChange">
                            <option value="">Select month</option>
                            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <div v-if="errors.period_month" class="text-danger small mt-1">{{ errors.period_month }}</div>
                    </div>
                </div>

                <div class="col-12" v-if="form.party_id && form.period_month">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-medium">Available TDS Deductions</span>
                        <VLoader v-if="loadingDeductions" loader-type="spinner" class="ms-2" />
                    </div>

                    <div v-if="deductions.length" class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                            <tr>
                                <th style="width:2rem">
                                    <input type="checkbox" class="form-check-input" v-model="allSelected" @change="toggleAll" />
                                </th>
                                <th>Category</th>
                                <th class="text-end">Base Amount</th>
                                <th class="text-end">TDS Amount</th>
                                <th>Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="d in deductions" :key="d.id">
                                <td>
                                    <input type="checkbox" class="form-check-input" :value="d.id" v-model="form.deduction_ids" />
                                </td>
                                <td>{{ d.tds_category_label || d.tds_category }}</td>
                                <td class="text-end">{{ formatMoney(d.base_amount) }}</td>
                                <td class="text-end">{{ formatMoney(d.tds_amount) }}</td>
                                <td>{{ d.created_at_date || '—' }}</td>
                            </tr>
                            </tbody>
                            <tfoot>
                            <tr class="bg-light fw-semibold">
                                <td colspan="2" class="text-end">Selected total:</td>
                                <td class="text-end">{{ formatMoney(selectedBaseTotal) }}</td>
                                <td class="text-end">{{ formatMoney(selectedTdsTotal) }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p v-else-if="!loadingDeductions" class="text-muted small mb-0">
                        No pending TDS deductions found for this party and month.
                    </p>
                    <div v-if="errors.deduction_ids" class="text-danger small mt-1">{{ errors.deduction_ids }}</div>
                </div>

                <div class="col-md-6">
                    <VDatepicker
                        id="challan_date"
                        v-model="form.challan_date"
                        input-type="date"
                        label="Challan Date"
                        required
                        @validate="validateField('challan_date')"
                        :error="errors.challan_date"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="challan_no"
                        v-model="form.challan_no"
                        label="Challan No"
                        placeholder="IRD challan reference number"
                    />
                </div>
                <div class="col-md-6">
                    <VDatepicker
                        id="payment_date"
                        v-model="form.payment_date"
                        input-type="date"
                        label="Payment Date"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="bank_name"
                        v-model="form.bank_name"
                        label="Bank Name"
                        placeholder="Bank where TDS was deposited"
                    />
                </div>
                <div class="col-12">
                    <VTextarea
                        id="remarks"
                        v-model="form.remarks"
                        label="Remarks"
                    />
                </div>

                <div class="col-12 text-end border-top pt-3 mt-1">
                    <button type="button" class="btn btn-cancel me-2" @click="closeModal">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                        Create Challan
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {computed, reactive, ref, watch} from 'vue';
import debounce from 'lodash/debounce';
import {storeToRefs} from 'pinia';
import {toast} from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors.js';
import {object, string, array, number} from 'yup';
import {useYup} from '@/helpers/yup.js';
import {formatMoney} from '@/helpers/formatMoney.js';
import {apiAdmin} from '@/helpers/api.js';
import {usePartyStore} from '@/stores/admin/party.js';
import {useTdsChallanStore} from '@/stores/admin/sales/tds-challan.js';
import {useDateHelper} from '@/composables/dateHelper.js';

const emit = defineEmits(['created']);
const open = defineModel('open', {default: false});

const partyStore = usePartyStore();
const challanStore = useTdsChallanStore();
const {parties} = storeToRefs(partyStore);
const {currentAdDate} = useDateHelper();

const isSubmitting = ref(false);
const loadingDeductions = ref(false);
const deductions = ref([]);
const allSelected = ref(false);

const months = [
    {value: 1, label: 'Shrawan (1)'},
    {value: 2, label: 'Bhadra (2)'},
    {value: 3, label: 'Ashwin (3)'},
    {value: 4, label: 'Kartik (4)'},
    {value: 5, label: 'Mangsir (5)'},
    {value: 6, label: 'Poush (6)'},
    {value: 7, label: 'Magh (7)'},
    {value: 8, label: 'Falgun (8)'},
    {value: 9, label: 'Chaitra (9)'},
    {value: 10, label: 'Baisakh (10)'},
    {value: 11, label: 'Jestha (11)'},
    {value: 12, label: 'Ashadh (12)'},
];

const initialState = () => ({
    party_id: '',
    period_month: '',
    challan_date: currentAdDate,
    challan_no: '',
    payment_date: '',
    bank_name: '',
    remarks: '',
    deduction_ids: [],
});

const form = reactive(initialState());

const validations = object({
    party_id: string().required('Party is required.'),
    period_month: string().required('Period month is required.'),
    challan_date: string().required('Challan date is required.'),
    deduction_ids: array().min(1, 'Select at least one deduction.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const debouncedPartySearch = debounce((query) => {
    partyStore.getParties({filter: {type: 'customer', limit: 50, search: query || ''}});
}, 300);

watch(open, (isOpen) => {
    if (isOpen) {
        partyStore.getParties({filter: {type: 'customer', limit: 50, search: ''}});
    }
});

async function loadDeductions() {
    if (!form.party_id || !form.period_month) {
        deductions.value = [];
        form.deduction_ids = [];
        return;
    }
    loadingDeductions.value = true;
    try {
        const res = await apiAdmin(`tds-deductions?party_id=${form.party_id}&period_month=${form.period_month}&unbundled=1`);
        deductions.value = res.data.data ?? [];
        form.deduction_ids = deductions.value.map((d) => d.id);
        allSelected.value = deductions.value.length > 0;
    } catch (e) {
        showErrors(e);
    } finally {
        loadingDeductions.value = false;
    }
}

function onPartyChange() {
    form.deduction_ids = [];
    deductions.value = [];
    loadDeductions();
}

function onMonthChange() {
    form.deduction_ids = [];
    deductions.value = [];
    loadDeductions();
}

function toggleAll() {
    if (allSelected.value) {
        form.deduction_ids = deductions.value.map((d) => d.id);
    } else {
        form.deduction_ids = [];
    }
}

watch(() => form.deduction_ids, (ids) => {
    allSelected.value = ids.length === deductions.value.length && deductions.value.length > 0;
});

const selectedDeductions = computed(() =>
    deductions.value.filter((d) => form.deduction_ids.includes(d.id)),
);

const selectedBaseTotal = computed(() =>
    selectedDeductions.value.reduce((s, d) => s + Number(d.base_amount || 0), 0),
);

const selectedTdsTotal = computed(() =>
    selectedDeductions.value.reduce((s, d) => s + Number(d.tds_amount || 0), 0),
);

async function submitForm() {
    const validated = await validateForm(validations, form);
    if (!validated) {
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await challanStore.storeChallan({
            party_id: form.party_id,
            period_month: form.period_month,
            challan_date: form.challan_date,
            challan_no: form.challan_no || null,
            payment_date: form.payment_date || null,
            bank_name: form.bank_name || null,
            remarks: form.remarks || null,
            deduction_ids: form.deduction_ids,
        });
        toast(res.status, res.data.message);
        emit('created', res.data.data);
        closeModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
}

function closeModal() {
    Object.assign(form, initialState());
    deductions.value = [];
    allSelected.value = false;
    errors.value = {};
    open.value = false;
}
</script>
