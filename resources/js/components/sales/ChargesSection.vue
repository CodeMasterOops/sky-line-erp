<template>
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fw-semibold small text-secondary text-uppercase">Additional Charges</span>
            <button v-if="!disabled" type="button" class="btn btn-sm btn-outline-secondary" @click="addCharge">
                <i class="ti ti-plus"></i> Add Charge
            </button>
        </div>

        <div v-if="charges.length > 0" class="table-responsive">
            <table class="table table-sm table-bordered charges-section-table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Account</th>
                        <th class="text-end">Amount</th>
                        <th>Tax</th>
                        <th class="text-end">Tax Amt</th>
                        <th v-if="!disabled" class="text-center" style="width:2.75rem"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(charge, i) in charges" :key="i">
                        <td>
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                v-model="charge.name"
                                placeholder="e.g. Freight charge"
                                :disabled="disabled"
                            />
                        </td>
                        <td>
                            <select class="form-select form-select-sm" v-model="charge.charge_type" :disabled="disabled">
                                <option v-for="ct in chargeTypes" :key="ct.value" :value="ct.value">
                                    {{ ct.label }}
                                </option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" v-model="charge.account_id" :disabled="disabled">
                                <option value="">Select account</option>
                                <option v-for="a in accounts.data" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </td>
                        <td style="width:7rem">
                            <input
                                type="number"
                                class="form-control form-control-sm text-end"
                                v-model="charge.amount"
                                min="0"
                                step="0.01"
                                :disabled="disabled"
                                @change="onAmountChange(i)"
                            />
                        </td>
                        <td>
                            <select
                                class="form-select form-select-sm"
                                v-model="charge.tax_id"
                                :disabled="disabled"
                                @change="onTaxChange(i)"
                            >
                                <option value="">None</option>
                                <option v-for="t in taxes.data" :key="t.id" :value="t.id">
                                    {{ t.name }} ({{ t.rate }}%)
                                </option>
                            </select>
                        </td>
                        <td style="width:7rem">
                            <input
                                type="number"
                                class="form-control form-control-sm text-end"
                                v-model="charge.tax_amount"
                                min="0"
                                step="0.01"
                                :disabled="disabled"
                            />
                        </td>
                        <td v-if="!disabled" class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeCharge(i)">
                                <i class="ti ti-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-else-if="disabled" class="text-muted small mb-0">No additional charges.</p>
    </div>
</template>

<script setup>
import {onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {useEnumStore} from '@/stores/admin/enum.js';
import {useAccountStore} from '@/stores/admin/accounting/account.js';
import {useTaxStore} from '@/stores/admin/settings/tax.js';

defineProps({
    disabled: {type: Boolean, default: false},
});

const charges = defineModel('charges', {default: () => []});

const enumStore = useEnumStore();
const accountStore = useAccountStore();
const taxStore = useTaxStore();

const {chargeTypes} = storeToRefs(enumStore);
const {accounts} = storeToRefs(accountStore);
const {taxes} = storeToRefs(taxStore);

onMounted(() => {
    enumStore.getChargeTypes();
    accountStore.getAccounts();
});

function addCharge() {
    charges.value.push({
        name: '',
        charge_type: chargeTypes.value[0]?.value ?? 'freight',
        account_id: '',
        amount: '0',
        tax_id: '',
        tax_amount: '0',
    });
}

function removeCharge(index) {
    charges.value.splice(index, 1);
}

function onTaxChange(index) {
    const charge = charges.value[index];
    const tax = taxes.value.data.find((t) => String(t.id) === String(charge.tax_id));
    charge.tax_amount = tax
        ? String(Math.round((Number(charge.amount) || 0) * (tax.rate / 100) * 100) / 100)
        : '0';
}

function onAmountChange(index) {
    if (charges.value[index].tax_id) {
        onTaxChange(index);
    }
}
</script>

<style scoped>
.charges-section-table th,
.charges-section-table td {
    vertical-align: middle;
}
</style>
