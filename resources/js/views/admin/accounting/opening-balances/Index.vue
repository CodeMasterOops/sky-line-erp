<template>
    <PageHeader title="Opening Balances" subtitle="Enter opening balances for accounts at fiscal year start" />

    <div class="card border-0 mb-3">
        <div class="card-body pb-1">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Fiscal Year</label>
                        <vue-select
                            v-model="fiscalYearId"
                            :options="fiscalYearOptions"
                            :reduce="opt => opt.value"
                            placeholder="Select Fiscal Year"
                        />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">As of Date</label>
                        <input type="date" class="form-control" v-model="asOfDate" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <button class="btn btn-outline-primary w-100" @click="addLine">
                            <i class="ti ti-plus me-1"></i> Add Account Line
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%">Account</th>
                            <th class="text-end">Debit (NPR)</th>
                            <th class="text-end">Credit (NPR)</th>
                            <th style="width: 60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, idx) in lines" :key="idx">
                            <td>
                                <vue-select
                                    v-model="line.account_id"
                                    :options="accountOptions"
                                    :reduce="opt => opt.value"
                                    placeholder="Select Account"
                                />
                            </td>
                            <td>
                                <input type="number" class="form-control text-end" v-model="line.dr_amount" min="0" step="0.01" placeholder="0.00" />
                            </td>
                            <td>
                                <input type="number" class="form-control text-end" v-model="line.cr_amount" min="0" step="0.01" placeholder="0.00" />
                            </td>
                            <td>
                                <button class="btn btn-sm btn-danger" @click="removeLine(idx)">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!lines.length">
                            <td colspan="4" class="text-center text-muted py-3">Add account lines above.</td>
                        </tr>
                    </tbody>
                    <tfoot class="table-secondary fw-bold">
                        <tr>
                            <td>Total</td>
                            <td class="text-end">{{ fmt(totalDr) }}</td>
                            <td class="text-end">{{ fmt(totalCr) }}</td>
                            <td></td>
                        </tr>
                        <tr v-if="Math.abs(totalDr - totalCr) > 0.005">
                            <td colspan="4" class="text-danger">
                                <i class="ti ti-alert-triangle me-1"></i>
                                Debit and Credit totals must be equal. Difference: {{ fmt(Math.abs(totalDr - totalCr)) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button class="btn btn-primary" @click="postOpeningBalance" :disabled="saving || lines.length < 2">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                    Post Opening Balance
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, computed, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';

const fiscalYearId = ref(null);
const asOfDate = ref(new Date().toISOString().split('T')[0]);
const lines = ref([]);
const saving = ref(false);
const fiscalYearOptions = ref([]);
const accountOptions = ref([]);

const totalDr = computed(() => lines.value.reduce((s, l) => s + (parseFloat(l.dr_amount) || 0), 0));
const totalCr = computed(() => lines.value.reduce((s, l) => s + (parseFloat(l.cr_amount) || 0), 0));

const loadFiscalYears = async () => {
    try {
        const res = await apiAdmin('admin-setting/fiscal-year', 'get');
        fiscalYearOptions.value = (res.data.data || []).map(fy => ({ label: fy.year_name, value: fy.id }));
    } catch (e) { /* ignore */ }
};

const loadAccounts = async () => {
    try {
        const res = await apiAdmin('account', 'get', { per_page: 500 });
        accountOptions.value = (res.data.data || []).map(a => ({
            label: `${a.name} (${a.code})`,
            value: a.id,
        }));
    } catch (e) { /* ignore */ }
};

const addLine = () => {
    lines.value.push({ account_id: null, dr_amount: 0, cr_amount: 0 });
};

const removeLine = (idx) => {
    lines.value.splice(idx, 1);
};

const postOpeningBalance = async () => {
    if (Math.abs(totalDr.value - totalCr.value) > 0.005) {
        toast('error', 'Debit and Credit totals must be equal.');
        return;
    }

    saving.value = true;
    try {
        const res = await apiAdmin('journal-voucher', 'post', {
            date: asOfDate.value,
            fiscal_year_id: fiscalYearId.value,
            type: 'opening-balance',
            remarks: 'Opening Balance Entry',
            items: lines.value.map(l => ({
                account_id: l.account_id,
                dr_amount: parseFloat(l.dr_amount) || 0,
                cr_amount: parseFloat(l.cr_amount) || 0,
                remarks: '',
            })),
        });
        toast('success', 'Opening balance posted successfully.');
        lines.value = [];
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

onMounted(() => {
    loadFiscalYears();
    loadAccounts();
});
</script>
