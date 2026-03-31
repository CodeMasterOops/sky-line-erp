<template>
    <PageHeader title="Fiscal Year" subtitle="Manage fiscal years" @refresh="fiscalYearStore.getFiscalYears()">
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
                    <table class="table table-responsive table-bordered">
                        <thead>
                        <tr>
                            <th>SN</th>
                            <th>Year</th>
                            <th>Code</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody class="align-middle">
                        <VLoader v-if="fiscalYears.loading" :colspan="7"/>
                        <template v-else-if="fiscalYears.data.length">
                            <tr v-for="(fiscalYear,index) in fiscalYears.data" :key="index">
                                <th>{{ index + 1 }}</th>
                                <td> {{ fiscalYear.year_name }}</td>
                                <td> {{ fiscalYear.year_code }}</td>
                                <td> {{ adToBs(fiscalYear.start_date) }}</td>
                                <td> {{ adToBs(fiscalYear.end_date) }}</td>
                                <td>
                                    <span v-if="fiscalYear.is_current" class="badge bg-success">Current</span>
                                    <span v-else class="badge bg-secondary">—</span>
                                </td>
                                <td style="width:140px;" class="text-center">
                                    <button
                                        v-if="!fiscalYear.is_current"
                                        type="button"
                                        @click.prevent="setCurrentFiscalYear(fiscalYear.id)"
                                        :disabled="settingCurrent"
                                        class="btn btn-sm btn-outline-success me-1">
                                        Set Current
                                    </button>
                                    <button
                                        type="button"
                                        @click.prevent="edit_fiscal_year_id=fiscalYear.id"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-edit"> </i>
                                    </button>
                                    <button  @click="deleteFiscalYear(fiscalYear.id)"
                                            type="button"
                                            class="btn btn-sm btn-outline-danger">
                                        <i class="fa fa-trash"> </i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="7" class="text-center">
                                No Result Found.
                            </td>
                        </tr>
                        </tbody>
                    </table>
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
import {useDateHelper} from "@/composables/dateHelper.js";
import { useFiscalYearStore } from '@/stores/super-admin/fiscal-year.js';

const {adToBs} = useDateHelper();
const fiscalYearStore = useFiscalYearStore();

onMounted(() => {
    fiscalYearStore.getFiscalYears();
})

const edit_fiscal_year_id = ref('');
const createModalOpened = ref(false);
const settingCurrent = ref(false);

const {fiscalYears} = storeToRefs(fiscalYearStore);

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

const setCurrentFiscalYear = async (id) => {
    settingCurrent.value = true;
    try {
        const res = await fiscalYearStore.setCurrentFiscalYear(id);
        toast(res.status, res.data.message);
    } catch (e) {
        showErrors(e);
    } finally {
        settingCurrent.value = false;
    }
}
</script>
