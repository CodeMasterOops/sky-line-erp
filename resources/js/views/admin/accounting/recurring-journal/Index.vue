<template>
    <PageHeader title="Recurring Journals" subtitle="Automate periodic journal entries">
        <template #actions>
            <button class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> Add Recurring Journal
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search journal name or remarks"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters"
        >
            <template #filters>
                <div class="dropdown me-2">
                    <a
                        href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown"
                    >
                        {{ filter.frequency ? frequencyLabel(filter.frequency) : 'All Frequencies' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1" @click="filter.frequency = ''">All Frequencies</a>
                        </li>
                        <li v-for="f in frequencies" :key="f.value">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1" @click="filter.frequency = f.value">{{ f.label }}</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a
                        href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown"
                    >
                        {{ filter.status === '' ? 'All Statuses' : filter.status === '1' ? 'Active' : 'Inactive' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="filter.status = ''">All Statuses</a></li>
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="filter.status = '1'">Active</a></li>
                        <li><a href="javascript:void(0);" class="dropdown-item rounded-1" @click="filter.status = '0'">Inactive</a></li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>
        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="journals"
                    :pagination="false"
                    :loading="loading"
                    @change="handleTableChange"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'frequency'">
                            <span class="text-capitalize">{{ record.frequency }}</span>
                        </template>
                        <template v-else-if="column.key === 'next_run_date'">
                            {{ formatDate(record.next_run_date) }}
                        </template>
                        <template v-else-if="column.key === 'last_run_at'">
                            {{ record.last_run_at ? formatDate(record.last_run_at) : '—' }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="record.is_active ? 'bg-success' : 'bg-secondary'">
                                {{ record.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a
                                        href="javascript:void(0);"
                                        class="me-2 p-2"
                                        title="Run Now"
                                        @click="runNow(record)"
                                    >
                                        <i class="ti ti-player-play text-success"></i>
                                    </a>
                                    <a
                                        href="javascript:void(0);"
                                        class="me-2 edit-icon p-2"
                                        @click="editJournal(record)"
                                    >
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        href="javascript:void(0);"
                                        class="p-2"
                                        @click="deleteJournal(record.id)"
                                    >
                                        <i class="ti ti-trash"></i>
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

    <!-- Create / Edit Modal -->
    <div v-if="showFormModal" class="modal d-block" style="background: rgba(0,0,0,0.5); z-index: 1055">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ editingJournal ? 'Edit' : 'Create' }} Recurring Journal
                    </h5>
                    <button type="button" class="btn-close" @click="showFormModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" v-model="form.name" placeholder="e.g. Monthly Rent" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Frequency <span class="text-danger">*</span></label>
                            <select class="form-select" v-model="form.frequency">
                                <option v-for="f in frequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <VDatepicker id="next_run_date" label="Next Run Date" v-model="form.next_run_date" required />
                        </div>
                        <div class="col-md-3">
                            <VDatepicker id="end_date" label="End Date" v-model="form.end_date" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <input type="text" class="form-control" v-model="form.remarks" />
                        </div>
                        <div v-if="editingJournal" class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" v-model="form.is_active" id="is-active-switch" />
                                <label class="form-check-label" for="is-active-switch">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Journal Lines</h6>
                        <button
                            class="btn btn-sm btn-outline-primary"
                            @click="form.items.push({ account_id: null, dr_amount: 0, cr_amount: 0 })"
                        >
                            <i class="ti ti-plus me-1"></i> Add Line
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50%">Account</th>
                                    <th class="text-end">Debit (NPR)</th>
                                    <th class="text-end">Credit (NPR)</th>
                                    <th style="width: 60px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in form.items" :key="idx">
                                    <td>
                                        <Multiselect
                                            v-model="item.account_id"
                                            :options="accountOptions"
                                            value-prop="value"
                                            label="label"
                                            :searchable="true"
                                            placeholder="Select account"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            class="form-control form-control-sm text-end"
                                            v-model="item.dr_amount"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                        />
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            class="form-control form-control-sm text-end"
                                            v-model="item.cr_amount"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                        />
                                    </td>
                                    <td class="text-center">
                                        <button
                                            class="btn btn-sm btn-outline-danger"
                                            @click="form.items.splice(idx, 1)"
                                        >
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr>
                                    <td>Total</td>
                                    <td class="text-end">{{ formatMoney(formTotalDr) }}</td>
                                    <td class="text-end">{{ formatMoney(formTotalCr) }}</td>
                                    <td></td>
                                </tr>
                                <tr v-if="form.items.length > 0 && Math.abs(formTotalDr - formTotalCr) > 0.005">
                                    <td colspan="4" class="text-danger small">
                                        <i class="ti ti-alert-triangle me-1"></i>
                                        Template must be balanced. Difference: {{ formatMoney(Math.abs(formTotalDr - formTotalCr)) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showFormModal = false">Cancel</button>
                    <button class="btn btn-primary" @click="saveJournal" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import { formatDate } from '@/helpers/helper.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import Swal from 'sweetalert2';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VPagination from '@/components/base/VPagination.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';

const journals = ref([]);
const meta = ref({ total: 0, current_page: 1, per_page: 15, from: null, to: null, last_page: 1 });
const loading = ref(false);
const showFormModal = ref(false);
const saving = ref(false);
const editingJournal = ref(null);
const accountOptions = ref([]);

const frequencies = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
];

const frequencyLabel = (v) => frequencies.find((f) => f.value === v)?.label ?? v;

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Frequency', key: 'frequency' },
    { title: 'Next Run', key: 'next_run_date' },
    { title: 'End Date', customRender: ({ record }) => record.end_date ? formatDate(record.end_date) : '—' },
    { title: 'Last Run', key: 'last_run_at' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action' },
];

const defaultForm = () => ({
    name: '',
    frequency: 'monthly',
    next_run_date: new Date().toISOString().split('T')[0],
    end_date: null,
    remarks: '',
    is_active: true,
    items: [
        { account_id: null, dr_amount: 0, cr_amount: 0 },
        { account_id: null, dr_amount: 0, cr_amount: 0 },
    ],
});

const form = ref(defaultForm());

const formTotalDr = computed(() =>
    form.value.items.reduce((s, i) => s + (parseFloat(i.dr_amount) || 0), 0)
);
const formTotalCr = computed(() =>
    form.value.items.reduce((s, i) => s + (parseFloat(i.cr_amount) || 0), 0)
);

const fetchJournals = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('recurring-journal', 'get', {
            page: filter.page,
            per_page: filter.limit,
            search: filter.search || undefined,
            frequency: filter.frequency || undefined,
            is_active: filter.status !== '' ? filter.status : undefined,
        });
        journals.value = res.data.data || [];
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
    defaults: { search: '', frequency: '', status: '', page: 1, limit: 15 },
    onFilter: fetchJournals,
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

const openCreate = () => {
    editingJournal.value = null;
    form.value = defaultForm();
    showFormModal.value = true;
};

const editJournal = (journal) => {
    editingJournal.value = journal;
    form.value = {
        name: journal.name,
        frequency: journal.frequency,
        next_run_date: journal.next_run_date,
        end_date: journal.end_date ?? null,
        remarks: journal.remarks ?? '',
        is_active: journal.is_active,
        items: journal.items?.length
            ? journal.items.map((i) => ({
                account_id: i.account_id,
                dr_amount: i.dr_amount,
                cr_amount: i.cr_amount,
            }))
            : defaultForm().items,
    };
    showFormModal.value = true;
};

const saveJournal = async () => {
    saving.value = true;
    try {
        if (editingJournal.value) {
            await apiAdmin(`recurring-journal/${editingJournal.value.id}`, 'put', form.value);
            toast('success', 'Recurring journal updated.');
        } else {
            await apiAdmin('recurring-journal', 'post', form.value);
            toast('success', 'Recurring journal created.');
        }
        showFormModal.value = false;
        await fetchJournals();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const runNow = async (journal) => {
    if (!journal.is_active) {
        toast('error', 'Journal is inactive. Activate it first.');
        return;
    }
    try {
        const res = await apiAdmin(`recurring-journal/${journal.id}/run-now`, 'post');
        toast('success', res.data.message);
        await fetchJournals();
    } catch (e) {
        showErrors(e);
    }
};

const deleteJournal = async (id) => {
    const result = await Swal.fire({
        title: 'Delete recurring journal?',
        text: 'This will remove the template. Posted journals will not be affected.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Delete',
    });
    if (!result.value) return;
    try {
        await apiAdmin(`recurring-journal/${id}`, 'delete');
        toast('success', 'Deleted.');
        await fetchJournals();
    } catch (e) {
        showErrors(e);
    }
};

loadAccounts();
</script>
