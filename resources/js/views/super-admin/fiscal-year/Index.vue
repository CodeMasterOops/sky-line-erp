<template>
    <PageHeader title="Fiscal Year" subtitle="Manage fiscal years" @refresh="fetchFiscalYears(true)">
        <template #actions>
            <button
                type="button"
                @click.prevent="createModalOpened=true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="fiscalYears.data"
                        :loading="fiscalYears.loading"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ index + 1 }}
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a class="me-2" href="javascript:void(0);"
                                       @click="edit_fiscal_year_id=record.id">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                       @click="deleteFiscalYear(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                </div>
            </div>
        </div>
    </section>
    <CreateFiscalYear v-model:create-modal-opened="createModalOpened"/>
    <EditFiscalYear v-model:fiscal_year_id="edit_fiscal_year_id"/>
</template>

<script setup>
import {onMounted, ref} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import CreateFiscalYear from './Create.vue';
import EditFiscalYear from './Edit.vue';
import {useFiscalYearStore} from '@/stores/super-admin/fiscal-year.js';

const fiscalYearStore = useFiscalYearStore();

onMounted(() => {
    fetchFiscalYears();
})

const edit_fiscal_year_id = ref('');
const createModalOpened = ref(false);

const fetchFiscalYears = (refetch = false) => {
    fiscalYearStore.getFiscalYears(refetch);
}

const {fiscalYears} = storeToRefs(fiscalYearStore);

const columns = [
    {
        title: 'SN',
        key: 'sn',
        width: 60,
    },
    {
        title: 'Year',
        dataIndex: 'year_name',
    },
    {
        title: 'Code',
        dataIndex: 'year_code',
    },
    {
        title: 'Start Date',
        dataIndex: 'start_date',
    },
    {
        title: 'End Date',
        dataIndex: 'end_date',
    },
    {
        title: 'Action',
        key: 'action',
        align: 'center',
    },
];

const deleteFiscalYear = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: "If you delete this, it will be gone forever.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: "Yes",
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await fiscalYearStore.deleteFiscalYear(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e)
            }
        }
    });
}
</script>
