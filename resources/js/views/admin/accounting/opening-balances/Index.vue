<template>
    <PageHeader title="Opening Balances" subtitle="Post opening balances for accounts at fiscal year start">
        <template #actions>
            <button class="btn btn-primary" @click="showForm = true">
                <i class="ti ti-circle-plus me-2"></i> Post Opening Balance
            </button>
        </template>
    </PageHeader>

    <!-- History list -->
    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search voucher no or remarks"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters"
        />
        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="entries"
                    :pagination="false"
                    :loading="loading"
                    @change="handleTableChange"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'date'">
                            {{ formatDate(record.date) }}
                        </template>
                        <template v-else-if="column.key === 'amount'">
                            {{ formatMoney(record.journal_items?.reduce((s, i) => s + parseFloat(i.dr_amount || 0), 0) || 0) }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge bg-success">approved</span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a
                                        href="javascript:void(0);"
                                        class="p-2"
                                        title="View Lines"
                                        @click="viewEntry(record)"
                                    >
                                        <i class="ti ti-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="meta" />
            </div>
        </div>
    </div>

    <!-- Post Opening Balance modal -->
    <VModal
        :show-modal="showForm"
        size="xl"
        title="Post Opening Balance"
        @close-click="showForm = false">
        <template #modal-body>
            <form @submit.prevent="postOpeningBalance" class="row g-3">
                <div class="col-md-6">
                    <VDatepicker id="form_date" label="As of Date" v-model="form.date" required />
                </div>
                <div class="col-md-6">
                    <VInput id="ob_remarks" v-model="form.remarks" label="Remarks" placeholder="Opening Balance Entry" />
                </div>

                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Account Lines</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addLine">
                            <i class="ti ti-plus me-1"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px">SN</th>
                                    <th style="min-width: 260px">Account</th>
                                    <th style="width: 160px" class="text-end">Debit (NPR)</th>
                                    <th style="width: 160px" class="text-end">Credit (NPR)</th>
                                    <th style="width: 60px" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(line, idx) in form.items" :key="idx">
                                    <td>{{ idx + 1 }}</td>
                                    <td>
                                        <VMultiselect
                                            v-model="line.account_id"
                                            :options="accountOptions"
                                            value-prop="value"
                                            name-prop="label"
                                            placeholder="Account"
                                        />
                                    </td>
                                    <td>
                                        <VInput
                                            v-model="line.dr_amount"
                                            input-type="number"
                                            input-class="form-control text-end"
                                            placeholder="0.00"
                                        />
                                    </td>
                                    <td>
                                        <VInput
                                            v-model="line.cr_amount"
                                            input-type="number"
                                            input-class="form-control text-end"
                                            placeholder="0.00"
                                        />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLine(idx)">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!form.items.length">
                                    <td colspan="5" class="text-center text-muted py-3">
                                        Click "Add Line" to add account entries.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr>
                                    <td colspan="2" class="text-end">Total</td>
                                    <td class="text-end">{{ formatMoney(totalDr) }}</td>
                                    <td class="text-end">{{ formatMoney(totalCr) }}</td>
                                    <td></td>
                                </tr>
                                <tr v-if="(totalDr > 0 || totalCr > 0) && Math.abs(totalDr - totalCr) > 0.005">
                                    <td colspan="5" class="text-danger small">
                                        <i class="ti ti-alert-triangle me-1"></i>
                                        Debit and Credit totals must be equal.
                                        Difference: {{ formatMoney(Math.abs(totalDr - totalCr)) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-cancel" @click="showForm = false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Post Opening Balance
                    </button>
                </div>
            </form>
        </template>
    </VModal>

    <!-- View detail modal -->
    <VModal
        :show-modal="!!viewingEntry"
        size="lg"
        @close-click="viewingEntry = null">
        <template #header>
            <div class="page-title">
                <h4 class="mb-0">{{ viewingEntry?.voucher_no }}</h4>
                <div class="text-muted small">{{ formatDate(viewingEntry?.date) }} · {{ viewingEntry?.remarks }}</div>
            </div>
        </template>
        <template #modal-body>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Account</th>
                            <th class="text-end">Debit (NPR)</th>
                            <th class="text-end">Credit (NPR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in viewingEntry?.journal_items" :key="item.id">
                            <td>{{ item.account?.name }} <span class="text-muted small">({{ item.account?.code }})</span></td>
                            <td class="text-end">{{ formatMoney(item.dr_amount) }}</td>
                            <td class="text-end">{{ formatMoney(item.cr_amount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td>Total</td>
                            <td class="text-end">
                                {{ formatMoney(viewingEntry?.journal_items?.reduce((s, i) => s + parseFloat(i.dr_amount || 0), 0) || 0) }}
                            </td>
                            <td class="text-end">
                                {{ formatMoney(viewingEntry?.journal_items?.reduce((s, i) => s + parseFloat(i.cr_amount || 0), 0) || 0) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </template>
    </VModal>
</template>

<script setup>
import { ref, computed } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import { formatDate } from '@/helpers/helper.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VPagination from '@/components/base/VPagination.vue';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import VDatepicker from '@/components/base/VDatepicker.vue';

const entries = ref([]);
const meta = ref({ total: 0, current_page: 1, per_page: 15, from: null, to: null, last_page: 1 });
const loading = ref(false);
const showForm = ref(false);
const saving = ref(false);
const viewingEntry = ref(null);
const accountOptions = ref([]);

const defaultForm = () => ({
    date: new Date().toISOString().split('T')[0],
    remarks: 'Opening Balance Entry',
    items: [
        { account_id: null, dr_amount: 0, cr_amount: 0 },
        { account_id: null, dr_amount: 0, cr_amount: 0 },
    ],
});

const form = ref(defaultForm());

const totalDr = computed(() =>
    form.value.items.reduce((s, l) => s + (parseFloat(l.dr_amount) || 0), 0)
);
const totalCr = computed(() =>
    form.value.items.reduce((s, l) => s + (parseFloat(l.cr_amount) || 0), 0)
);

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Voucher No', dataIndex: 'voucher_no', key: 'voucher_no' },
    { title: 'Date', key: 'date' },
    { title: 'Remarks', dataIndex: 'remarks', key: 'remarks' },
    { title: 'Total Debit', key: 'amount' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action' },
];

const fetchEntries = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('opening-balance', 'get', {
            page: filter.page,
            per_page: filter.limit,
            search: filter.search || undefined,
        });
        entries.value = res.data.data || [];
        const m = res.data.meta || res.data;
        meta.value = {
            total: m.total ?? 0,
            current_page: m.current_page ?? filter.page,
            per_page: m.per_page ?? filter.limit,
            from: m.from ?? null,
            to: m.to ?? null,
            last_page: m.last_page ?? 1,
        };
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', page: 1, limit: 15 },
    onFilter: fetchEntries,
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => meta.value),
    filter,
});

const loadAccounts = async () => {
    try {
        const res = await apiAdmin('account', 'get', { per_page: 500 });
        accountOptions.value = (res.data.data || []).map((a) => ({
            label: `${a.name} (${a.code})`,
            value: a.id,
        }));
    } catch { /* ignore */ }
};

const addLine = () => {
    form.value.items.push({ account_id: null, dr_amount: 0, cr_amount: 0 });
};

const removeLine = (idx) => {
    form.value.items.splice(idx, 1);
};

const viewEntry = (record) => {
    viewingEntry.value = record;
};

const postOpeningBalance = async () => {
    if (Math.abs(totalDr.value - totalCr.value) > 0.005) {
        toast('error', 'Debit and Credit totals must be equal.');
        return;
    }

    saving.value = true;
    try {
        await apiAdmin('opening-balance', 'post', form.value);
        toast('success', 'Opening balance posted successfully.');
        showForm.value = false;
        form.value = defaultForm();
        await fetchEntries();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

loadAccounts();
</script>
