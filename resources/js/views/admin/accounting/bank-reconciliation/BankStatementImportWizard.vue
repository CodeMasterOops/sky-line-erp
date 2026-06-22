<template>
    <VModal :show-modal="open" size="lg" title="Import Bank Statement" @close-click="close">
        <template #modal-body>
            <ul class="nav nav-tabs mb-3">
                <li class="nav-item" v-for="(label, idx) in stepLabels" :key="idx">
                    <span class="nav-link" :class="{ active: step === idx, disabled: idx > step }">{{ label }}</span>
                </li>
            </ul>

            <!-- Step 0: Upload -->
            <div v-if="step === 0">
                <p class="text-muted small">Upload a bank statement as CSV or XLSX. Columns are mapped in the next step.</p>
                <input type="file" class="form-control" accept=".csv,.xlsx,.txt" @change="onFileSelect" />
                <div v-if="parseError" class="alert alert-danger mt-3 mb-0 small">{{ parseError }}</div>
            </div>

            <!-- Step 1: Map columns -->
            <div v-else-if="step === 1">
                <p class="text-muted small">Map your file's columns to the statement fields. Date and at least one of Debit / Credit are required.</p>
                <div class="table-responsive" style="max-height: 320px">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr><th>Statement field</th><th>File column</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="f in targetFields" :key="f.key">
                                <td>{{ f.label }} <span v-if="f.required" class="text-danger">*</span></td>
                                <td>
                                    <select v-model="mapping[f.key]" class="form-select form-select-sm">
                                        <option value="">— Skip —</option>
                                        <option v-for="(h, i) in headers" :key="i" :value="i">{{ h || `Column ${i + 1}` }}</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="mapError" class="alert alert-danger mt-2 mb-0 small">{{ mapError }}</div>
            </div>

            <!-- Step 2: Preview -->
            <div v-else-if="step === 2">
                <p class="text-muted small">
                    {{ mappedRows.length }} row(s) parsed. Duplicates already in the system are skipped automatically on import.
                </p>
                <div class="table-responsive" style="max-height: 320px">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th><th>Description</th><th>Reference</th>
                                <th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in previewRows" :key="i">
                                <td class="text-nowrap">{{ r.transaction_date }}</td>
                                <td>{{ r.description }}</td>
                                <td class="text-muted small">{{ r.reference }}</td>
                                <td class="text-end">{{ r.debit || '—' }}</td>
                                <td class="text-end">{{ r.credit || '—' }}</td>
                                <td class="text-end">{{ r.balance ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="mappedRows.length > previewRows.length" class="text-muted small mb-0">
                    Showing first {{ previewRows.length }} of {{ mappedRows.length }}.
                </p>
                <div v-if="result" class="alert alert-success mt-2 mb-0 small">
                    Imported <strong>{{ result.imported }}</strong> line(s)<span v-if="result.skipped"> ({{ result.skipped }} duplicate(s) skipped)</span>.
                </div>
            </div>

            <div class="modal-footer border-0 px-0 pb-0">
                <button type="button" class="btn btn-secondary" @click="close">Close</button>
                <button v-if="step > 0 && !result" type="button" class="btn btn-outline-primary" @click="step--">Back</button>
                <button v-if="step === 0" type="button" class="btn btn-primary" :disabled="!headers.length" @click="step = 1">Next</button>
                <button v-if="step === 1" type="button" class="btn btn-primary" @click="goPreview">Preview</button>
                <button v-if="step === 2 && !result" type="button" class="btn btn-primary" :disabled="importing || !mappedRows.length" @click="submit">
                    <span v-if="importing" class="spinner-border spinner-border-sm me-1"></span>
                    Import {{ mappedRows.length }} row(s)
                </button>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import { ref } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';

const emit = defineEmits(['imported']);

const open = ref(false);
const bankAccountId = ref(null);

const step = ref(0);
const stepLabels = ['Upload', 'Map columns', 'Preview'];

const headers = ref([]);
const dataRows = ref([]);
const mapping = ref({});
const parseError = ref('');
const mapError = ref('');
const importing = ref(false);
const result = ref(null);

const targetFields = [
    { key: 'transaction_date', label: 'Date', required: true },
    { key: 'description', label: 'Description', required: false },
    { key: 'reference', label: 'Reference', required: false },
    { key: 'debit', label: 'Debit (money out)', required: false },
    { key: 'credit', label: 'Credit (money in)', required: false },
    { key: 'balance', label: 'Balance', required: false },
];

const mappedRows = ref([]);
const previewRows = ref([]);

const reset = () => {
    step.value = 0;
    headers.value = [];
    dataRows.value = [];
    mapping.value = {};
    mappedRows.value = [];
    previewRows.value = [];
    parseError.value = '';
    mapError.value = '';
    result.value = null;
};

const show = (id) => {
    reset();
    bankAccountId.value = id;
    open.value = true;
};

const close = () => {
    open.value = false;
    if (result.value) emit('imported');
};

defineExpose({ show });

const guessMapping = () => {
    const find = (...keys) => headers.value.findIndex(
        (h) => keys.some((k) => String(h || '').toLowerCase().includes(k)),
    );
    const set = (field, idx) => { if (idx >= 0) mapping.value[field] = idx; };
    set('transaction_date', find('date', 'miti'));
    set('description', find('description', 'narration', 'particular', 'detail'));
    set('reference', find('reference', 'ref', 'cheque', 'chq'));
    set('debit', find('debit', 'withdraw', 'dr'));
    set('credit', find('credit', 'deposit', 'cr'));
    set('balance', find('balance', 'bal'));
};

const onFileSelect = async (e) => {
    const file = e.target.files?.[0];
    parseError.value = '';
    if (!file) return;
    try {
        const XLSX = await import('xlsx');
        const buffer = await file.arrayBuffer();
        const wb = XLSX.read(buffer, { type: 'array', cellDates: true });
        const ws = wb.Sheets[wb.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' });
        const nonEmpty = rows.filter((r) => r.some((c) => String(c).trim() !== ''));
        if (nonEmpty.length < 2) {
            parseError.value = 'The file has no data rows.';
            return;
        }
        headers.value = nonEmpty[0].map((h) => String(h).trim());
        dataRows.value = nonEmpty.slice(1);
        mapping.value = {};
        guessMapping();
    } catch {
        parseError.value = 'Could not read the file. Please upload a valid CSV or XLSX.';
    }
};

const toNumber = (v) => {
    if (v === '' || v === null || v === undefined) return 0;
    const n = parseFloat(String(v).replace(/[^0-9.-]/g, ''));
    return Number.isFinite(n) ? Math.abs(n) : 0;
};

const toDate = (v) => {
    if (v instanceof Date && !isNaN(v)) return v.toISOString().slice(0, 10);
    const s = String(v).trim();
    // dd/mm/yyyy or dd-mm-yyyy → yyyy-mm-dd
    const m = s.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/);
    if (m) return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`;
    const d = new Date(s);
    return isNaN(d) ? s : d.toISOString().slice(0, 10);
};

const goPreview = () => {
    mapError.value = '';
    if (mapping.value.transaction_date === undefined || mapping.value.transaction_date === '') {
        mapError.value = 'Please map the Date column.';
        return;
    }
    if ((mapping.value.debit === undefined || mapping.value.debit === '') &&
        (mapping.value.credit === undefined || mapping.value.credit === '')) {
        mapError.value = 'Please map at least one of Debit or Credit.';
        return;
    }
    const col = (key, row) => {
        const idx = mapping.value[key];
        return idx === undefined || idx === '' ? '' : row[idx];
    };
    mappedRows.value = dataRows.value
        .map((row) => ({
            transaction_date: toDate(col('transaction_date', row)),
            description: String(col('description', row) || '').trim() || null,
            reference: String(col('reference', row) || '').trim() || null,
            debit: toNumber(col('debit', row)),
            credit: toNumber(col('credit', row)),
            balance: mapping.value.balance === undefined || mapping.value.balance === '' ? null : toNumber(col('balance', row)),
        }))
        .filter((r) => r.transaction_date && (r.debit > 0 || r.credit > 0));
    previewRows.value = mappedRows.value.slice(0, 20);
    step.value = 2;
};

const submit = async () => {
    if (!bankAccountId.value || !mappedRows.value.length) return;
    importing.value = true;
    try {
        const res = await apiAdmin(
            `bank-reconciliation/bank-accounts/${bankAccountId.value}/import-lines`,
            'post',
            { lines: mappedRows.value },
        );
        result.value = res.data;
        toast('success', `Imported ${res.data.imported ?? 0} line(s).`);
    } catch (e) {
        showErrors(e);
    } finally {
        importing.value = false;
    }
};
</script>
