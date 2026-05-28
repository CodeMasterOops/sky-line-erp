<template>
    <div>
        <PageHeader
            title="Fiscal Year"
            subtitle="Manage fiscal years"
            @refresh="fetch"
        >
            <template #actions>
                <button
                    type="button"
                    @click.prevent="createModalOpened = true"
                    class="btn btn-primary d-flex align-items-center"
                >
                    <i class="ti ti-circle-plus me-2"></i> Add New
                </button>
            </template>
        </PageHeader>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <super-admin-settings-sidebar></super-admin-settings-sidebar>
                <div class="card flex-fill mb-0">
                    <div class="card-header">
                        <h4 class="fs-18 fw-bold">Fiscal year</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <a-table
                                class="table datanew table-hover table-center mb-0"
                                :columns="columns"
                                :data-source="fiscalYears.data"
                                :loading="fiscalYears.loading"
                                :pagination="false"
                            >
                                <template #bodyCell="{ column, record, index }">
                                    <template v-if="column.key === 'sn'">
                                        {{ (fiscalYears.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                                    </template>
                                    <template v-if="column.key === 'action'">
                                        <div class="action-icon d-inline-flex">
                                            <a
                                                class="me-2"
                                                href="javascript:void(0);"
                                                @click="edit_fiscal_year_id = record.id"
                                            >
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            <a
                                                href="javascript:void(0);"
                                                @click="deleteFiscalYear(record.id)"
                                            >
                                                <i class="ti ti-trash"></i>
                                            </a>
                                        </div>
                                    </template>
                                </template>
                            </a-table>
                            <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="fiscalYears.meta" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <CreateFiscalYear v-model:create-modal-opened="createModalOpened"/>
    <EditFiscalYear v-model:fiscal_year_id="edit_fiscal_year_id"/>
</template>

<script setup>
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import CreateFiscalYear from './Create.vue';
import EditFiscalYear from './Edit.vue';
import { useFiscalYearStore } from '@/stores/super-admin/fiscal-year.js';
import { adToBsDate } from '@/helpers/helper.js';

const fiscalYearStore = useFiscalYearStore();
const edit_fiscal_year_id = ref('');
const createModalOpened = ref(false);
const settingCurrent = ref(false);
const { fiscalYears } = storeToRefs(fiscalYearStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => fiscalYearStore.getFiscalYears({ filter }),
    defaults: { page: 1, limit: 10 },
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Year', dataIndex: 'year_name' },
    { title: 'Code', dataIndex: 'year_code' },
    {
        title: 'Start Date',
        customRender: ({ record }) => adToBsDate(record.start_date),
    },
    {
        title: 'End Date',
        customRender: ({ record }) => adToBsDate(record.end_date),
    },
    { title: 'Action', key: 'action', align: 'center' },
];

const deleteFiscalYear = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: 'If you delete this, it will be gone forever.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes',
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await fiscalYearStore.deleteFiscalYear(id);
                toast(res.status, res.data.message);
                fetch();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const setCurrentFiscalYear = async (id) => {
    settingCurrent.value = true;
    try {
        const res = await fiscalYearStore.setCurrentFiscalYear(id);
        toast(res.status, res.data.message);
        fetch();
    } catch (e) {
        showErrors(e);
    } finally {
        settingCurrent.value = false;
    }
};
</script>
