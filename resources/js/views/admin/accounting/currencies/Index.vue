<template>
    <PageHeader title="Currencies" subtitle="View foreign exchange rates (Base: NPR)" @refresh="fetchCurrencies">
        <template #actions>
            <button type="button" class="btn btn-primary" @click.prevent="openCreate">
                <i class="ti ti-circle-plus me-1"></i>Add Currency
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <div class="search-set">
                    <div class="search-input">
                        <a href="javascript:void(0);" class="btn-searchset">
                            <i class="ti ti-search fs-14 feather-search"></i>
                        </a>
                        <input
                            v-model="filter.search"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Search currencies"
                            @input="debouncedFetch"
                        />
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="currencies"
                        :loading="loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (listMeta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'exchange_rate'">
                                <span class="text-end d-block">{{ record.exchange_rate }}</span>
                            </template>
                            <template v-if="column.key === 'rate_date'">
                                {{ formatDate(record.rate_date) }}
                            </template>
                            <template v-if="column.key === 'base'">
                                <span v-if="record.is_base" class="badge badge-sm badge-info">Base</span>
                                <span v-else class="text-muted">—</span>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a href="javascript:void(0);" class="me-2" @click="editCurrency(record)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a
                                        v-if="!record.is_base"
                                        href="javascript:void(0);"
                                        @click="deleteCurrency(record.id)"
                                    >
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="listMeta" />
                </div>
            </div>
        </div>
    </section>

    <VModal
        :show-modal="showForm"
        size="md"
        :title="editingCurrency ? 'Edit Currency' : 'Add Currency'"
        @close-click="showForm = false">
        <template #modal-body>
            <form @submit.prevent="saveCurrency" class="row g-3">
                <div class="col-12" v-if="!editingCurrency">
                    <VInput
                        id="currency_code"
                        v-model="form.code"
                        label="Currency Code (3 chars)"
                        input-class="form-control text-uppercase"
                        placeholder="USD, INR, EUR..."
                        required
                    />
                </div>
                <div class="col-12">
                    <VInput id="currency_name" v-model="form.name" label="Currency Name" placeholder="US Dollar" required />
                </div>
                <div class="col-12">
                    <VInput id="currency_symbol" v-model="form.symbol" label="Symbol" placeholder="Rs." />
                </div>
                <div class="col-12">
                    <VInput
                        id="currency_rate"
                        v-model="form.exchange_rate"
                        label="Exchange Rate (1 unit = ? NPR)"
                        input-type="number"
                        required
                    />
                </div>
                <div class="col-12">
                    <VDatepicker id="rate_date" label="Rate Date" v-model="form.rate_date" />
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-cancel" @click="showForm = false">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save
                    </button>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import { formatDate } from '@/helpers/helper.js';
import Swal from 'sweetalert2';
import VPagination from '@/components/base/VPagination.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';

const currencies = ref([]);
const loading = ref(false);
const showForm = ref(false);
const saving = ref(false);
const editingCurrency = ref(null);
const listMeta = ref({ total: 0, current_page: 1, per_page: 10, from: null, to: null, last_page: 1 });

const filter = reactive({ search: '', page: 1, limit: 10 });

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Code', dataIndex: 'code', key: 'code' },
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Symbol', dataIndex: 'symbol', key: 'symbol' },
    { title: 'Exchange Rate (to NPR)', key: 'exchange_rate', align: 'right' },
    { title: 'Rate Date', key: 'rate_date' },
    { title: 'Base', key: 'base' },
    { title: 'Action', key: 'action', align: 'center' },
];

const defaultForm = () => ({ code: '', name: '', symbol: '', exchange_rate: 1, rate_date: null });
const form = ref(defaultForm());

const fetchCurrencies = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams({
            page: String(filter.page),
            limit: String(filter.limit),
            ...(filter.search ? { search: filter.search } : {}),
        });
        const res = await apiAdmin(`currency?${params}`);
        currencies.value = res.data.data || [];
        listMeta.value = res.data.meta ?? { total: currencies.value.length };
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const debouncedFetch = debounce(() => {
    const onFirstPage = filter.page === 1;
    filter.page = 1;
    if (onFirstPage) {
        fetchCurrencies();
    }
}, 300);

watch(() => [filter.page, filter.limit], () => {
    fetchCurrencies();
});

onMounted(() => {
    fetchCurrencies();
});

const openCreate = () => {
    editingCurrency.value = null;
    form.value = defaultForm();
    showForm.value = true;
};

const editCurrency = (cur) => {
    editingCurrency.value = cur;
    form.value = {
        ...cur,
        rate_date: cur.rate_date ? cur.rate_date.slice(0, 10) : null,
    };
    showForm.value = true;
};

const saveCurrency = async () => {
    saving.value = true;
    try {
        if (editingCurrency.value) {
            await apiAdmin(`currency/${editingCurrency.value.id}`, 'put', form.value);
            toast('success', 'Currency updated.');
        } else {
            await apiAdmin('currency', 'post', {...form.value, code: form.value.code?.toUpperCase()});
            toast('success', 'Currency added.');
        }
        showForm.value = false;
        await fetchCurrencies();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const deleteCurrency = async (id) => {
    const result = await Swal.fire({
        title: 'Delete currency?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    });
    if (!result.isConfirmed) {
        return;
    }
    try {
        await apiAdmin(`currency/${id}`, 'delete');
        toast('success', 'Deleted.');
        await fetchCurrencies();
    } catch (e) {
        showErrors(e);
    }
};
</script>
