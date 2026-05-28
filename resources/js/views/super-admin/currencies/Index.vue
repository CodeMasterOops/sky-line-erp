<template>
    <PageHeader title="Currencies" subtitle="Manage global exchange rates (Base: NPR)" @refresh="fetch">
        <template #actions>
            <button type="button" class="btn btn-primary" @click.prevent="createModalOpened = true">
                <i class="ti ti-circle-plus me-1"></i>Add Currency
            </button>
        </template>
    </PageHeader>

    <div class="row">
        <div class="col-lg-4 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">Total Currencies</p>
                        <h4>{{ stats.total }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-primary flex-shrink-0">
                        <i class="ti ti-currency-rupee fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">Active</p>
                        <h4>{{ stats.active }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-success flex-shrink-0">
                        <i class="ti ti-check fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">Foreign Currencies</p>
                        <h4>{{ stats.foreign }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-skyblue flex-shrink-0">
                        <i class="ti ti-world fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

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
                        :data-source="currencies.data"
                        :loading="currencies.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (currencies.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'exchange_rate'">
                                <span class="text-end d-block">{{ record.exchange_rate }}</span>
                            </template>
                            <template v-if="column.key === 'rate_date'">
                                {{ formatDate(record.rate_date) }}
                            </template>
                            <template v-if="column.key === 'base'">
                                <span v-if="record.is_base" class="badge badge-sm badge-info">Base (NPR)</span>
                                <span v-else class="text-muted">—</span>
                            </template>
                            <template v-if="column.key === 'status'">
                                <span :class="['badge badge-sm', record.is_active ? 'badge-success' : 'badge-danger']">
                                    {{ record.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a href="javascript:void(0);" class="me-2" @click="editCurrencyId = record.id">
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
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="currencies.meta" />
                </div>
            </div>
        </div>
    </section>

    <CreateCurrency v-model:create-modal-opened="createModalOpened"/>
    <EditCurrency v-model:currency-id="editCurrencyId"/>
</template>

<script setup>
import { ref } from 'vue';
import debounce from 'lodash/debounce';
import Swal from 'sweetalert2';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { formatDate } from '@/helpers/helper.js';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import CreateCurrency from './Create.vue';
import EditCurrency from './Edit.vue';
import { useCurrencyStore } from '@/stores/super-admin/currency';

const currencyStore = useCurrencyStore();
const { currencies, stats } = storeToRefs(currencyStore);

const createModalOpened = ref(false);
const editCurrencyId = ref('');

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => currencyStore.getCurrencies({ filter }),
    defaults: { search: '', page: 1, limit: 10 },
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Code', dataIndex: 'code', key: 'code' },
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Symbol', dataIndex: 'symbol', key: 'symbol' },
    { title: 'Exchange Rate (to NPR)', key: 'exchange_rate', align: 'right' },
    { title: 'Rate Date', key: 'rate_date' },
    { title: 'Base', key: 'base' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action', align: 'center' },
];

const debouncedFetch = debounce(() => {
    const onFirstPage = filter.page === 1;
    filter.page = 1;
    if (onFirstPage) {
        fetch();
    }
}, 300);

const deleteCurrency = async (id) => {
    Swal.fire({
        title: 'Delete this currency?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    }).then(async (result) => {
        if (result.value) {
            try {
                const res = await currencyStore.deleteCurrency(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
